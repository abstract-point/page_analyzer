<?php

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use App\Database\Connection;
use Valitron\Validator;

session_start();

$container = new Container();
AppFactory::setContainer($container);

$container->set('view', function() {
    return Twig::create('../templates', ['cache' => false]);
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::create();
$app->add(TwigMiddleware::createFromContainer($app));
$app->addErrorMiddleware(true, true, true);

$app->get(
    '/',
    function (Request $request, Response $response, $args) {
        return $this->get('view')->render(
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

        $v = new Validator($params['url']);
        $v->rule('required', 'name');
        $v->rule('url', 'name');

        // Валидация полученного от пользователя url
        if ($v->validate()) {
            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
            $name = $params['url']['name'];
        } else {
            // Errors
            $errors = $v->errors();
        }

        // TODO: Сначала проверяем, есть ли уже такой сайт в базе,
        // если нет, добавляем, иначе записываем flash и выводим его
        $pdo = Connection::get()->connect();

        $sql = 'INSERT INTO urls(name) VALUES(:name)';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':name', $name);

        $stmt->execute();
        dump($pdo->lastInsertId('urls_id_seq'));
    }
)->setName('addUrls');

$app->run();
