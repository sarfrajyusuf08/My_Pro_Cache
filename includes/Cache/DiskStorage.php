<?php

namespace MyProCache\Cache;

use MyProCache\Options\Manager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use function array_diff;
use function array_unique;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function sha1;
use function time;
use function unlink;
use function wp_mkdir_p;
use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;
use const LOCK_EX;

class DiskStorage implements StorageInterface
{
    private Manager $options;

    private string $baseDir;

    private string $pagesDir;

    private string $metaDir;

    private string $tagsDir;

    private string $uriDir;

    public function __construct( Manager $options )
    {
        $this->options = $options;
        $this->baseDir = $options->get_cache_dir();
        $this->pagesDir = $this->baseDir . DIRECTORY_SEPARATOR . 'pages';
        $this->metaDir  = $this->baseDir . DIRECTORY_SEPARATOR . 'meta';
        $this->tagsDir  = $this->baseDir . DIRECTORY_SEPARATOR . 'tags';
        $this->uriDir   = $this->baseDir . DIRECTORY_SEPARATOR . 'uris';

        foreach ( array( $this->pagesDir, $this->metaDir, $this->tagsDir, $this->uriDir ) as $dir ) {
            if ( ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
    }

    public function get( string $key ): ?array
    {
        $hash = $this->hash_key( $key );
        $meta_file = $this->metaDir . DIRECTORY_SEPARATOR . $hash . '.json';
        $content_file = $this->pagesDir . DIRECTORY_SEPARATOR . $hash . '.html';

        if ( ! file_exists( $meta_file ) || ! file_exists( $content_file ) ) {
            return null;
        }

        $meta = json_decode( (string) file_get_contents( $meta_file ), true );
        if ( ! is_array( $meta ) ) {
            return null;
        }

        $meta['content'] = (string) file_get_contents( $content_file );

        return $meta;
    }

    public function set( string $key, array $payload, array $tags = array(), ?int $ttl = null ): bool
    {
        $hash         = $this->hash_key( $key );
        $meta_file    = $this->metaDir . DIRECTORY_SEPARATOR . $hash . '.json';
        $content_file = $this->pagesDir . DIRECTORY_SEPARATOR . $hash . '.html';

        $meta = array(
            'key'     => $key,
            'uri'     => $payload['uri'] ?? '',
            'created' => time(),
            'ttl'     => $ttl,
            'headers' => $payload['headers'] ?? array(),
            'tags'    => $tags,
            'status'  => $payload['status'] ?? 200,
        );

        file_put_contents( $content_file, $payload['content'] ?? '', LOCK_EX );
        file_put_contents( $meta_file, json_encode( $meta, JSON_PRETTY_PRINT ), LOCK_EX );

        $this->index_tags( $hash, $tags );
        if ( ! empty( $meta['uri'] ) ) {
            $this->index_uri( $hash, $meta['uri'] );
        }

        return true;
    }

    public function delete( string $key ): void
    {
        $this->delete_by_hash( $this->hash_key( $key ) );
    }

    public function clear(): void
    {
        $this->delete_directory( $this->pagesDir );
        $this->delete_directory( $this->metaDir );
        $this->delete_directory( $this->tagsDir );
        $this->delete_directory( $this->uriDir );

        foreach ( array( $this->pagesDir, $this->metaDir, $this->tagsDir, $this->uriDir ) as $dir ) {
            if ( ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
    }

    public function purge_tags( array $tags ): void
    {
        foreach ( $tags as $tag ) {
            $tag_file = $this->tagsDir . DIRECTORY_SEPARATOR . sha1( $tag ) . '.json';
            if ( ! file_exists( $tag_file ) ) {
                continue;
            }

            $hashes = json_decode( (string) file_get_contents( $tag_file ), true );
            if ( is_array( $hashes ) ) {
                foreach ( $hashes as $hash ) {
                    $this->delete_by_hash( $hash );
                }
            }

            unlink( $tag_file );
        }
    }

    public function purge_uri( string $uri ): void
    {
        $uri_file = $this->uriDir . DIRECTORY_SEPARATOR . sha1( $uri ) . '.json';
        if ( ! file_exists( $uri_file ) ) {
            return;
        }

        $hashes = json_decode( (string) file_get_contents( $uri_file ), true );
        if ( is_array( $hashes ) ) {
            foreach ( $hashes as $hash ) {
                $this->delete_by_hash( $hash );
            }
        }

        unlink( $uri_file );
    }

    private function hash_key( string $key ): string
    {
        return sha1( $key );
    }

    private function index_tags( string $hash, array $tags ): void
    {
        foreach ( array_unique( $tags ) as $tag ) {
            $tag_file = $this->tagsDir . DIRECTORY_SEPARATOR . sha1( $tag ) . '.json';
            $hashes = array();
            if ( file_exists( $tag_file ) ) {
                $decoded = json_decode( (string) file_get_contents( $tag_file ), true );
                if ( is_array( $decoded ) ) {
                    $hashes = $decoded;
                }
            }
            if ( ! in_array( $hash, $hashes, true ) ) {
                $hashes[] = $hash;
            }
            file_put_contents( $tag_file, json_encode( $hashes ), LOCK_EX );
        }
    }

    private function index_uri( string $hash, string $uri ): void
    {
        $uri_file = $this->uriDir . DIRECTORY_SEPARATOR . sha1( $uri ) . '.json';
        $hashes   = array();
        if ( file_exists( $uri_file ) ) {
            $decoded = json_decode( (string) file_get_contents( $uri_file ), true );
            if ( is_array( $decoded ) ) {
                $hashes = $decoded;
            }
        }
        if ( ! in_array( $hash, $hashes, true ) ) {
            $hashes[] = $hash;
        }
        file_put_contents( $uri_file, json_encode( $hashes ), LOCK_EX );
    }

    private function remove_from_tags( string $hash, array $tags ): void
    {
        foreach ( $tags as $tag ) {
            $tag_file = $this->tagsDir . DIRECTORY_SEPARATOR . sha1( $tag ) . '.json';
            if ( ! file_exists( $tag_file ) ) {
                continue;
            }
            $decoded = json_decode( (string) file_get_contents( $tag_file ), true );
            if ( ! is_array( $decoded ) ) {
                unlink( $tag_file );
                continue;
            }
            $filtered = array_values( array_diff( $decoded, array( $hash ) ) );
            if ( empty( $filtered ) ) {
                unlink( $tag_file );
            } else {
                file_put_contents( $tag_file, json_encode( $filtered ), LOCK_EX );
            }
        }
    }

    private function remove_from_uri( string $hash, string $uri ): void
    {
        $uri_file = $this->uriDir . DIRECTORY_SEPARATOR . sha1( $uri ) . '.json';
        if ( ! file_exists( $uri_file ) ) {
            return;
        }
        $decoded = json_decode( (string) file_get_contents( $uri_file ), true );
        if ( ! is_array( $decoded ) ) {
            unlink( $uri_file );
            return;
        }
        $filtered = array_values( array_diff( $decoded, array( $hash ) ) );
        if ( empty( $filtered ) ) {
            unlink( $uri_file );
        } else {
            file_put_contents( $uri_file, json_encode( $filtered ), LOCK_EX );
        }
    }

    private function delete_by_hash( string $hash ): void
    {
        $meta_file    = $this->metaDir . DIRECTORY_SEPARATOR . $hash . '.json';
        $content_file = $this->pagesDir . DIRECTORY_SEPARATOR . $hash . '.html';

        if ( file_exists( $meta_file ) ) {
            $meta = json_decode( (string) file_get_contents( $meta_file ), true );
            if ( isset( $meta['tags'] ) && is_array( $meta['tags'] ) ) {
                $this->remove_from_tags( $hash, $meta['tags'] );
            }
            if ( ! empty( $meta['uri'] ) ) {
                $this->remove_from_uri( $hash, $meta['uri'] );
            }
            unlink( $meta_file );
        }

        if ( file_exists( $content_file ) ) {
            unlink( $content_file );
        }
    }

    private function delete_directory( string $dir ): void
    {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getPathname() );
            } else {
                unlink( $file->getPathname() );
            }
        }

        rmdir( $dir );
    }
}
