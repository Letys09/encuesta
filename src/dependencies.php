<?php

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

$container['rpt_renderer'] = function ($c) {
    $settings = $c->get('settings')['rpt_renderer'];
    return new \Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));
    return $logger;
};

// Database
$container['db'] = function($c){
    $connectionString = $c->get('settings')['connectionString'];
    
    $pdo = new PDO($connectionString['dns'], $connectionString['user'], $connectionString['pass']);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

    return new FluentPDO($pdo); 
};

// Models
$container['model'] = function($c) {
    return (object)[
        'pregunta' => new App\Model\PreguntaModel($c->db),
        'encuesta' => new App\Model\EncuestaModel($c->db),
        'universo' => new App\Model\UniversoModel($c->db),
        'url' => new App\Model\UrlModel($c->db),
        'valor_qr' => new App\Model\ValorQrModel($c->db),
        'intento' => new App\Model\IntentoModel($c->db),
        'respuesta' => new App\Model\RespuestaModel($c->db),
        'usuario' => new App\Model\UsuarioModel($c->db),
        'reporte' => new App\Model\ReporteModel($c->db),
        'transaction' => new App\Lib\Transaction($c->db),
    ];
};