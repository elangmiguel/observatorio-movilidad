<?php

return[

    /*
     * URL del proyecto de supabase
     */
    'url' => env('SUPABASE_URL'),

    /*
     * Secreto JWT heredado (HS256). Solo se usa si el proyecto tiene los tokens
     * con ell secreto compartido.
     * Con claves de firma asimetricas se deja vacio y los tokens se verifican
     * con las claves publicas del proyecto (JWKS).
     */
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),

    /*
     * Clave service_role: solo para el servidor.
     * Pemrite invitar usuarios mediante la API de administracion de Supabase Auth.
     */
    'service_role_key' => env('SUPABASE_SECRET_KEY', env('SUPABASE_SERVICE_ROLE_KEY')),

    /*
     * URL de las claves publicas (JWKS)
     */

    'jwks_url' => env('SUPABASE_JWKS_URL'),

    /*
     * Audiencia que deben traer los tokens de usuarios autenticados
     */
    'audience' => env('SUPABASE_JWT_AUDIENCE', 'authenticated'),

    /*
     * Minutos que se conservan en cache las claves publicas (JWKS).
     */
    'jwks_cache_minutos' => 10,
];
