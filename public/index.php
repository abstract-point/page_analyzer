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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use DiDom\Document;
use Illuminate\Support;

session_start();

$container = new Container();
AppFactory::setContainer($container);

$container->set(
    'view',
    function () {
        return Twig::create('../templates', ['cache' => false]);
    }
);

$container->set(
    'flash',
    function () {
        return new \Slim\Flash\Messages();
    }
);

$app = AppFactory::create();
$app->add(TwigMiddleware::createFromContainer($app));
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get(
    '/page-analyzer',
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
    '/page-analyzer/urls',
    function (Request $request, Response $response) use ($router) {
        $params = $request->getParsedBodyParam('url');

        $v = new Validator($params);
        $v->rule('required', 'name');
        $v->rule('url', 'name');
        $v->rule('lengthBetween', 'name', 1, 255);
        $name = $params['name'];

        if ($v->validate()) {
            $parsedUrl = parse_url($name);
            $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";
        } else {
            $errors = $v->errors();
            $template = 'main.html';
            return $this->get('view')->render(
                $response->withStatus(422),
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
            $id = $pdo->lastInsertId() ?: '';
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
    '/page-analyzer/urls',
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
    '/page-analyzer/urls/{id}',
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
    '/page-analyzer/urls/{url_id}/checks',
    function (Request $request, Response $response, $args) use ($router) {
        $id = $args['url_id'];
        $name = $request->getParsedBodyParam('url')['name'];

        $now = Carbon::now();
        $now->setTimezone('Europe/Moscow')->toDateTimeString();

        $client = new Client();

        try {
            $res = $client->request('GET', $name);
            $code = $res->getStatusCode();
            $body = $res->getBody()->getContents();
            $this->get('flash')->addMessage('success', 'Страница успешно проверена');
        } catch (ConnectException $e) {
            $this->get('flash')->addMessage('unsuccess', 'Произошла ошибка при проверке, не удалось подключиться');
            $url = $router->urlFor('url', ['id' => $id]);

            return $response->withRedirect($url, 302);
        } catch (ClientException $e) {
            $this->get('flash')->addMessage('info', 'Проверка была выполнена успешно, но сервер ответил с ошибкой');
            $code = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->getHandlerContext()['errno'] === 60) {
                $this->get('flash')->addMessage('unsuccess', 'Ошибка SSL: проблема с сертификатом сайта');
            } else {
                $this->get('flash')->addMessage('unsuccess', 'Произошла ошибка при проверке');
            }
            $url = $router->urlFor('url', ['id' => $id]);

            return $response->withRedirect($url, 302);
        }

        $document = new Document();
        $document->loadHtml($body);

        $title = optional($document->first('title'))->text();
        $h1 = optional($document->first('h1'))->text();
        $description = optional($document->first('meta[name=description]'))->getAttribute('content');

        $pdo = Connection::get()->connect();
        $sqlInsert = 'INSERT INTO url_checks(url_id, status_code, h1, title, description, created_at)
                      VALUES(:url_id, :status_code, :h1, :title, :description, :created_at)';
        $stmInsert = $pdo->prepare($sqlInsert);
        $params = [
            ':url_id' => $id,
            ':status_code' => $code,
            ':h1' => $h1,
            ':title' => $title,
            ':description' => $description,
            ':created_at' => $now,
        ];
        $stmInsert->execute($params);

        $url = $router->urlFor('url', ['id' => $id]);

        return $response->withRedirect($url, 302);
    }
)->setName('checks');

$app->run();
