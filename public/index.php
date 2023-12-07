<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Database\Connection;



$app = AppFactory::create();
$twig = Twig::create('../templates', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));

$app->get(
    '/',
    function (Request $request, Response $response, $args) {
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'main.html',
            [
            'first' => 'My name is Ivan!'
            ]
        );
    }
)->setName('main');

$app->post(
    '/urls',
    function (Request $request, Response $response,) {
        $params = $request->getParsedBody();
        $name = $params['url']['name'];

        $pdo = Connection::get()->connect();

        $sql = 'INSERT INTO urls(name) VALUES(:name)';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':name', $name);

        $stmt->execute();
        dump($pdo->lastInsertId('urls_id_seq'));
    }
)->setName('urls');

$app->run();
