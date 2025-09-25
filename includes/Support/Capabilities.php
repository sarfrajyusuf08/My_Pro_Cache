<?php

namespace MyProCache\Support;

class Capabilities
{
    public static function manage_capability(): string
    {
        $default = 'manage_options';

        return (string) apply_filters( 'my_pro_cache_manage_capability', $default );
    }
}
