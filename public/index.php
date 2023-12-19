<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
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

$container->set(
    'view', function () {
        return Twig::create('../templates', ['cache' => false]);
    }
);

$container->set(
    'flash', function () {
        return new \Slim\Flash\Messages();
    }
);

$app = AppFactory::create();
$app->add(TwigMiddleware::createFromContainer($app));
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get(
    '/',
    function (Request $request, Response $response, $args) {
        $template = 'main.html';
        return $this->get('view')->render(
            $response,
            'layout.html',
            [
            'errors' => [],
            'template' => $template,
            ]
        );
    }
)->setName('main');

$app->post(
    '/urls',
    function (Request $request, Response $response) use ($router) {
        $params = $request->getParsedBody();

        $v = new Validator($params['url']);
        $v->rule('required', 'name');
        $v->rule('url', 'name');
        $v->rule('lengthBetween', 'name', 1, 255);
        $name = $params['url']['name'];

        if ($v->validate()) {
            $parsedUrl = parse_url($name);
            $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
        } else {
            $errors = $v->errors();
            $template = 'main.html';
            return $this->get('view')->render(
                $response,
                'layout.html',
                [
                'errors' => $errors,
                'template' => $template,
                'name' => $name,
                ]
            );
        }

        $pdo = Connection::get()->connect();

        $sqlFind = 'SELECT id FROM urls WHERE name = :name';
        $stmt = $pdo->prepare($sqlFind);
        $stmt->bindValue(':name', $normalizedUrl);
        $stmt->execute();
        $finded = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$finded) {
            $now = Carbon::now()->toDateTimeString();

            $sqlInsert = 'INSERT INTO urls(name, created_at) VALUES(:name, :now)';
            $stmt = $pdo->prepare($sqlInsert);
            $stmt->bindValue(':name', $normalizedUrl);
            $stmt->bindValue(':now', $now);
            $stmt->execute();

            $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
            $id = $pdo->lastInsertId('urls_id_seq');
            $url = $router->urlFor('url', ['id' => $id]);

            return $response->withRedirect($url, 302);
        } else {
            $id = $finded['id'];
            $this->get('flash')->addMessage('unsuccess', 'Страница уже существует');
            $url = $router->urlFor('url', ['id' => $id]);

            return $response->withRedirect($url, 302);
        }
    }
)->setName('addUrls');

$app->get(
    '/urls',
    function (Request $request, Response $response) {
        $template = 'urls.html';

        $pdo = Connection::get()->connect();
        $sql = 'SELECT urls.id, urls.name,
                    MAX(url_checks.created_at) AS last_check,
                    url_checks.status_code AS status_code
                    FROM urls
                    LEFT JOIN url_checks
                    ON urls.id = url_checks.url_id
                    GROUP BY urls.id, status_code
                    ORDER BY urls.created_at
                    DESC NULLS LAST';
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // dump($sites);die;

        return $this->get('view')->render(
            $response,
            'layout.html',
            [
                'template' => $template,
                'sites' => $sites,
            ]
        );
    }
)->setName('urls');

$app->get(
    '/urls/{id}',
    function (Request $request, Response $response, $args) {
        $template = 'url.html';
        $id = $args['id'];

        $pdo = Connection::get()->connect();

        $sqlUrl = 'SELECT * FROM urls WHERE id=:id';
        $stmUrl = $pdo->prepare($sqlUrl);
        $stmUrl->bindValue(':id', $id);
        $stmUrl->execute();
        $site = $stmUrl->fetch(PDO::FETCH_ASSOC);

        $sqlCheks = 'SELECT * FROM url_checks WHERE url_id=:id';
        $stmChecks = $pdo->prepare($sqlCheks);
        $stmChecks->bindValue(':id', $id);
        $stmChecks->execute();
        $checks = $stmChecks->fetchAll(PDO::FETCH_ASSOC);

        $messages = $this->get('flash')->getMessages();

        return $this->get('view')->render(
            $response,
            'layout.html',
            [
            'template' => $template,
            'site' => $site,
            'checks' => $checks,
            'messages' => $messages,
            ]
        );
    }
)->setName('url');

$app->post(
    '/urls/{url_id}/checks',
    function (Request $request, Response $response, $args) use ($router) {
        $id = $args['url_id'];
        $now = Carbon::now()->toDateTimeString();

        $pdo = Connection::get()->connect();
        $sql = 'INSERT INTO url_checks(url_id, created_at) VALUES(:id, :now)';
        $stm = $pdo->prepare($sql);
        $stm->bindValue(':id', $id);
        $stm->bindValue(':now', $now);
        $stm->execute();

        $this->get('flash')->addMessage('success', 'Страница успешно проверена');

        $url = $router->urlFor('url', ['id' => $id]);

        return $response->withRedirect($url, 302);
    }
)->setName('checks');

$app->run();
