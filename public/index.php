<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

session_start();

$container = new Container();

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
//$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];
$users = [];
$handle = fopen("users.txt", 'r');
if ($handle) {
    while (($buffer = fgets($handle)) !== false) {
        $user = json_decode($buffer, true);
        $users[$user['id']] = $user;
    }
    fclose($handle);
}

$app->get('/', function ($request, $response) {
    return $response->write('Welcome to Slim!');
});

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    if ($term) {
        $users = array_filter($users, function ($user) use ($term) {
            return strpos($user, $term) !== false;
        });
    }
    $messages = $this->get('flash')->getMessages();
    $params = ['users' => $users, 'term' => $term, 'messages' => $messages];
    return $this->get('renderer')->render($response, "users/index.phtml", $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) use ($users) {
    $id = count($users) + 1;
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => $id],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('userNew');

$app->post('/users', function ($request, $response) use ($users) {
    $user = $request->getParsedBodyParam('user');
    if ($user) {
        $this->get('flash')->addMessage('success', 'Add user');
        $user['id'] = count($users) + 1;
        file_put_contents('users.txt', json_encode($user) . "\n", FILE_APPEND | LOCK_EX);
    }
    return $response->withRedirect('/users', 302);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->get('/courses/{courseId}/lessons/{id}', function ($request, $response, array $args) {
    $courseId = $args['courseId'];
    $id = $args['id'];
    return $response->write("Course id: {$courseId}<br>")
        ->write("lesson id: {$id}");
});

$app->get('/users/{id}',
    function ($request, $response, $args) use ($users) {
        if (!isset($users[$args['id']])) {
            $response = $response->withStatus(404);
            return $response;
        }
        $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
        // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
        // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
        return $this->get('renderer')->render($response, 'users/show.phtml', $params);
    }
);

$app->get('/foo', function ($req, $res) {
    // Добавление флеш-сообщения. Оно станет доступным на следующий HTTP-запрос.
    // 'success' — тип флеш-сообщения. Используется при выводе для форматирования.
    // Например можно ввести тип success и отражать его зелёным цветом (на Хекслете такого много)
    $this->get('flash')->addMessage('success', 'This is a message');
    $this->get('flash')->addMessage('success', 'This is a message2');
    $this->get('flash')->addMessage('success', 'This is a message3');
    $this->get('flash')->addMessage('success', 'This is a message4');
    return $res->withRedirect('/bar');
});

$app->get('/bar', function ($req, $res, $args) {
    // Извлечение flash сообщений установленных на предыдущем запросе
    $messages = $this->get('flash')->getMessages();
    return $res->write(print_r($messages,true));
});

$app->run();
