<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RouteListCommand extends Command
{
    protected $signature = 'route:list';
    protected $description = 'Display all registered routes';

    public function handle()
    {
        global $app;
        $routes = $app->router->getRoutes();
        
        $rows = [];
        foreach ($routes as $route) {
            $rows[] = [
                'method' => $route['method'],
                'uri'    => $route['uri'],
                'action' => $route['action']['uses'] ?? ($route['action']['as'] ?? 'Closure'),
            ];
        }

        $this->table(['Method', 'URI', 'Action'], $rows);
    }
}