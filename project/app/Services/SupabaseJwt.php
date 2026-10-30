<?php

namespace App\Services;

use App\Models\Usuario;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use UnexpectedValueException;

/**
 * Autentica las peticiones con el token de acceso que emite Supabase Auth.
 * Verifica la firma, el emisor, la audiencia y la vigencia del JWT, y carga el
 * perfil del usuario en la tabla usuarios (cuyo id es el de la cuenta de Supabase).
 */
class SupabaseJwt
{
    public function usuario(Request $request): ?Usuario
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        try {
            $claims = $this->verificar($token);
        } catch (Throwable $e) {
            logger()
                ->warning(
                    'JWT rechazado',
                    [
                        'error' => $e::class . ': ' . $e->getMessage()
                    ]
                );
            return null;
        }

        // Los tokens anónimos o de servicio no corresponden a una persona.
        if (($claims->role ?? null) !== 'authenticated' || empty($claims->sub)) {
            return null;
        }

        // Las cuentas dadas de baja tienen borrado lógico y no se encuentran (401).
        $usuario = Usuario::find($claims->sub);

        if (! $usuario) {
            return null;
        }

        // Cuenta registrada por la propia persona que aún espera la aprobación de un administrador.
        if (! $usuario->activo) {
            throw new AccessDeniedHttpException('Su cuenta está pendiente de aprobación.');
        }

        return $usuario;
    }

    private function verificar(string $token): object
    {
        $cabecera = $this->cabecera($token);
        $algoritmo = $cabecera['alg'] ?? '';

        JWT::$leeway = 60;

        $claves = match ($algoritmo) {
            'HS256' => new Key($this->secretoHs256(), 'HS256'),
            'ES256', 'RS256' => $this->clavesPublicas($cabecera['kid'] ?? null, $algoritmo),
            default => throw new UnexpectedValueException('Algoritmo de firma no admitido.'),
        };

        $claims = JWT::decode($token, $claves);

        if (($claims->iss ?? null) !== $this->emisor()) {
            throw new UnexpectedValueException('Emisor del token no válido.');
        }

        if (! in_array(config('supabase.audience'), (array) ($claims->aud ?? []), true)) {
            throw new UnexpectedValueException('Audiencia del token no válida.');
        }

        return $claims;
    }

    private function cabecera(string $token): array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            throw new UnexpectedValueException('Token mal formado.');
        }

        $cabecera = json_decode(JWT::urlsafeB64Decode($partes[0]), true);

        if (! is_array($cabecera)) {
            throw new UnexpectedValueException('Cabecera del token no válida.');
        }

        return $cabecera;
    }

    private function secretoHs256(): string
    {
        $secreto = config('supabase.jwt_secret');

        if (! $secreto) {
            throw new RuntimeException('SUPABASE_JWT_SECRET no está configurado.');
        }

        return $secreto;
    }

    /**
     * Claves públicas del proyecto indexadas por kid. Si el token usa una clave
     * que no está en caché (por ejemplo, tras una rotación), se vuelve a consultar una vez.
     *
     * @return array<string, Key>
     */
    private function clavesPublicas(?string $kid, string $algoritmo): array
    {
        for ($intento = 0; $intento < 2; $intento++) {
            $claves = JWK::parseKeySet($this->jwks(), $algoritmo);

            if ($kid !== null && isset($claves[$kid])) {
                return $claves;
            }

            Cache::forget('supabase.jwks');
        }

        throw new UnexpectedValueException('No se encontró la clave pública del token.');
    }

    private function jwks(): array
    {
        return Cache::remember(
            'supabase.jwks',
            now()->addMinutes(config('supabase.jwks_cache_minutos', 10)),
            fn () => Http::timeout(5)
                ->get($this->emisor() . '/.well-known/jwks.json')
                ->throw()
                ->json()
        );
    }

    private function emisor(): string
    {
        return rtrim((string) config('supabase.url'), '/') . '/auth/v1';
    }
}
