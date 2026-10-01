<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Materiales y entregas de aula virtual — acceso privado.
        | Se sirven solo a través de un endpoint autenticado.
        | Future: reemplazar driver por 's3' + MinIO sin cambiar lógica.
        |----------------------------------------------------------------------
        */
        'materiales' => [
            'driver'     => 'local',
            'root'       => storage_path('app/materiales'),
            'visibility' => 'private',
            'throw'      => false,
        ],

        'entregas' => [
            'driver'     => 'local',
            'root'       => storage_path('app/entregas'),
            'visibility' => 'private',
            'throw'      => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
