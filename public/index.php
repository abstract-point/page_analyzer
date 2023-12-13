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
use Carbon\Carbon;

session_start();

$container = new Container();
AppFactory::setContainer($container);

$container->set('view', function () {
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
            'errors' => [],
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
        $v->rule('lengthBetween', 'name', 1, 255);

        // Валидация полученного от пользователя url
        if ($v->validate()) {
            $name = $params['url']['name'];
            $parsedUrl = parse_url($name);
            $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
            //dump($normalizedUrl);die;
        } else {
            // Errors
            $errors = $v->errors();
            return $this->get('view')->render(
                $response,
                'main.html',
                [
                'errors' => $errors,
                ]
            );
            // 
        }

        // TODO: Сначала проверяем, есть ли уже такой сайт в базе,
        // если нет, добавляем, иначе записываем flash и выводим его
        $pdo = Connection::get()->connect();

        $sqlFind = 'SELECT id FROM urls WHERE name = :name';
        $stmt = $pdo->prepare($sqlFind);
        $stmt->bindValue(':name', $normalizedUrl);
        $stmt->execute();
        $finded = $stmt->fetch(PDO::FETCH_ASSOC);
        //dump($finded);die;

        if (!$finded) {
            $sqlInsert = 'INSERT INTO urls(name) VALUES(:name)';
            $stmt = $pdo->prepare($sqlInsert);
    
            $stmt->bindValue(':name', $normalizedUrl);
    
            $stmt->execute();
            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
            dump($pdo->lastInsertId('urls_id_seq'));
        } else {
            $id = $finded['id'];
            $this->get('flash')->addMessage('unsuccess', 'Страница уже существует');
        }


        
    }
)->setName('addUrls');

$app->run();
