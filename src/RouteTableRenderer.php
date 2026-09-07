<?php
namespace Roolith\Route;

/**
 * Renders registered routes as a terminal-friendly ASCII table.
 *
 * Columns: Method, Path, Name, Action, Middleware.
 * Missing values are shown as "-".
 *
 * Usage:
 * echo RouteTableRenderer::render($router->getRouteList());
 */
class RouteTableRenderer
{
    /**
     * Render route list as an ASCII table string.
     *
     * @param array $routes Route list from RouterBase::getRouteList().
     * @return string ASCII table, or "No routes registered." when empty.
     */
    public static function render(array $routes): string
    {
        if (count($routes) === 0) {
            return "No routes registered.\n";
        }

        $headers = ['Method', 'Path', 'Name', 'Action', 'Middleware'];
        $rows = [];

        foreach ($routes as $route) {
            $rows[] = [
                $route['method'] ?? '-',
                $route['path'] ?? '-',
                self::formatName($route),
                self::formatAction($route),
                self::formatMiddleware($route),
            ];
        }

        $widths = [];

        foreach ($headers as $index => $header) {
            $widths[$index] = mb_strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index], mb_strlen($cell));
            }
        }

        $separator = '+' . implode('+', array_map(function ($width) {
            return str_repeat('-', $width + 2);
        }, $widths)) . "+\n";

        $output = $separator;
        $output .= self::renderRow($headers, $widths);
        $output .= $separator;

        foreach ($rows as $row) {
            $output .= self::renderRow($row, $widths);
        }

        $output .= $separator;

        return $output;
    }

    /**
     * Render a single table row with padding.
     *
     * @param array $cells Ordered cell values.
     * @param array $widths Column widths from header and row content.
     * @return string Padded row ending with newline.
     */
    private static function renderRow(array $cells, array $widths): string
    {
        $parts = [];

        foreach ($cells as $index => $cell) {
            $parts[] = ' ' . self::padCell($cell, $widths[$index]) . ' ';
        }

        return '|' . implode('|', $parts) . "|\n";
    }

    /**
     * Pad cell value to column width using multibyte-safe length.
     *
     * @param string $value Cell value.
     * @param int $width Target column width.
     * @return string Padded value.
     */
    private static function padCell(string $value, int $width): string
    {
        $padding = $width - mb_strlen($value);

        if ($padding > 0) {
            return $value . str_repeat(' ', $padding);
        }

        return $value;
    }

    /**
     * Format route name, using "-" for missing or empty names.
     *
     * @param array $route Single route entry.
     * @return string
     */
    private static function formatName(array $route): string
    {
        if (!isset($route['name']) || $route['name'] === '') {
            return '-';
        }

        return (string) $route['name'];
    }

    /**
     * Format route action: handler string, Closure label, or redirect target.
     *
     * Redirect routes render as "Redirect to <target> (<code>)".
     *
     * @param array $route Single route entry.
     * @return string
     */
    private static function formatAction(array $route): string
    {
        if (isset($route['redirect'])) {
            $target = (string) $route['redirect'];

            if (isset($route['code'])) {
                return 'Redirect to ' . $target . ' (' . $route['code'] . ')';
            }

            return 'Redirect to ' . $target;
        }

        if (!isset($route['execute'])) {
            return '-';
        }

        $execute = $route['execute'];

        if (is_string($execute)) {
            return $execute === '' ? '-' : $execute;
        }

        if ($execute instanceof \Closure) {
            return 'Closure';
        }

        if (is_object($execute)) {
            return get_class($execute);
        }

        if (is_array($execute)) {
            return 'Callable';
        }

        return '-';
    }

    /**
     * Format route middleware as a comma-separated list, "-" when none.
     *
     * @param array $route Single route entry.
     * @return string
     */
    private static function formatMiddleware(array $route): string
    {
        if (!isset($route['middleware'])) {
            return '-';
        }

        $middleware = $route['middleware'];

        if (is_string($middleware)) {
            return $middleware === '' ? '-' : $middleware;
        }

        if (is_array($middleware)) {
            $names = array_map(function ($item) {
                if (is_string($item)) {
                    return $item;
                }

                if (is_object($item)) {
                    return get_class($item);
                }

                return (string) $item;
            }, $middleware);

            $names = array_filter($names, function ($name) {
                return $name !== '';
            });

            return count($names) > 0 ? implode(', ', $names) : '-';
        }

        if (is_object($middleware)) {
            return get_class($middleware);
        }

        return '-';
    }
}
