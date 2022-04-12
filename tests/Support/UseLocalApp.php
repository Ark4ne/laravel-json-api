<?php

namespace Test\Support;

trait UseLocalApp
{
    public function useLocalApp()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../app/migrations.php');
    }

    protected function defineRoutes($router)
    {
        include __DIR__ . '/../app/routes.php';
    }
}
