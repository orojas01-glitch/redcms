(function (root) {
    'use strict';

    function buildTarget(encodedAlias, depth, pathname, hash) {
        var alias;
        var parentCount = Number(depth) - 1;

        if (parentCount < 0 || parentCount > 2 || Math.floor(parentCount) !== parentCount) {
            return '';
        }

        try {
            alias = decodeURIComponent(encodedAlias || '');
        } catch (error) {
            return '';
        }

        if (!/^[a-z0-9][a-z0-9_-]*$/.test(alias)) {
            return '';
        }

        var segments = String(pathname || '').split('/').filter(function (segment) {
            return segment !== '';
        });
        if (segments.length < parentCount) {
            return '';
        }

        var targetSegments = segments.slice(0, parentCount);
        targetSegments.push(encodeURIComponent(alias));

        var targetHash = typeof hash === 'string' && hash.charAt(0) === '#' ? hash : '';
        return '/' + targetSegments.join('/') + '/' + targetHash;
    }

    function redirect(encodedAlias, depth) {
        if (!root.location || typeof root.location.assign !== 'function') {
            return false;
        }

        var target = buildTarget(
            encodedAlias,
            depth,
            root.location.pathname,
            root.location.hash
        );
        if (target === '') {
            return false;
        }

        root.location.assign(target);
        return true;
    }

    function redirectPath(encodedPath) {
        var path;
        if (!root.location || typeof root.location.assign !== 'function') {
            return false;
        }
        try {
            path = decodeURIComponent(encodedPath || '');
        } catch (error) {
            return false;
        }
        if (!/^\/(?:[a-z0-9][a-z0-9_-]*\/)*$/.test(path)) {
            return false;
        }
        root.location.assign(path);
        return true;
    }

    root.RED_ADMIN_AREA_RENAME = {
        buildTarget: buildTarget,
        redirect: redirect,
        redirectPath: redirectPath
    };
}(window));
