<?php
/**
 * Compatibility helpers for public URLs inherited from flat HTML websites.
 */

if (!function_exists('red_public_route_article_alias')) {
    function red_public_route_article_alias($segment, $segmentCount)
    {
        $segment = is_string($segment) ? $segment : '';
        if ((int) $segmentCount !== 2
            || preg_match('/\A([A-Za-z0-9][A-Za-z0-9_-]*)\.html\z/i', $segment, $matches) !== 1
        ) {
            return $segment;
        }

        $alias = (string) $matches[1];
        return strtolower($alias) === 'index' ? '' : $alias;
    }
}

if (!function_exists('red_public_route_legacy_canonical_path')) {
    function red_public_route_legacy_canonical_path($path, $themeId)
    {
        if ((string) $themeId !== 'adriana-granobles') {
            return null;
        }

        static $redirects = [
            '/index.html' => '/',
            '/clases-de-musica.html' => '/clases-de-musica/',
            '/instrumentos.html' => '/clases-de-musica/instrumentos',
            '/canto.html' => '/voz-y-transformacion/',
            '/estudio-de-grabacion.html' => '/estudio-de-grabacion/',
            '/testimonios.html' => '/clases-de-musica/testimonios',
            '/escuela-canto.html' => '/clases-de-musica/canto',
            '/escuela-piano.html' => '/clases-de-musica/piano',
            '/escuela-guitarra.html' => '/clases-de-musica/guitarra',
            '/escuela-bateria.html' => '/clases-de-musica/bateria',
            '/escuela-percusion.html' => '/clases-de-musica/percusion',
            '/escuela-bajo.html' => '/clases-de-musica/bajo',
            '/escuela-flauta.html' => '/clases-de-musica/flauta-traversa',
            '/escuela-clarinete.html' => '/clases-de-musica/clarinete',
            '/escuela-teoria-musical.html' => '/clases-de-musica/teoria-musical',
            '/escuela-composicion-produccion.html' => '/clases-de-musica/composicion-y-produccion',
            '/escuela-violin.html' => '/clases-de-musica/violin',
            '/coaching-ontologico.html' => '/voz-y-transformacion/coaching-ontologico',
            '/canto-terapeutico.html' => '/voz-y-transformacion/canto-terapeutico',
            '/composicion.html' => '/estudio-de-grabacion/composicion',
            '/produccion-musical.html' => '/estudio-de-grabacion/produccion-musical',
            '/clases-de-musica-online-para-ninos.html' => '/clases-de-musica/clases-para-ninos',
            '/programa-cuda.html' => '/programa-cuda',
            '/el-cantautor.html' => '/el-cantautor',
            '/bodas-y-eventos.html' => '/bodas-y-eventos',
            '/la-voz-que-sana.html' => '/voz-y-transformacion/la-voz-que-sana',
            '/sobre-adriana.html' => '/sobre-adriana',
            '/contacto.html' => '/contacto',
        ];

        $path = is_string($path) ? $path : '';
        return $redirects[$path] ?? null;
    }
}
