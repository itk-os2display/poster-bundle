angular.module('posterModule').directive('posterEditTextsTool', [
    function () {
        'use strict';

        return {
            restrict: 'E',
            replace: true,
            scope: {
                slide: '=',
                close: '&',
                tool: '='
            },
            link: function (scope) {
                if (!scope.slide.options.overrides) {
                    scope.slide.options.overrides = {};
                }
            },
            templateUrl: '/bundles/os2displayposter/apps/posterModule/posterEditTextsTool.html'
        };
    }
]);
