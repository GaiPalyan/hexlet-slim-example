<?php

$autoloadPath1 = __DIR__ . '/../../../autoload.php';
$autoloadPath2 = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoloadPath1)) {
    include_once $autoloadPath1;
} else {
    include_once $autoloadPath2;
}

use Slim\Middleware\MethodOverrideMiddleware;
use function Symfony\Component\String\s;
use Slim\Factory\AppFactory;
use Tightenco\Collect\Support\Collection;
use DI\Container;

/* Иммитация базы данных */
$filePath = '..' . DIRECTORY_SEPARATOR . 'DB' . DIRECTORY_SEPARATOR . 'userData.json';
$usersRepo = json_decode(file_get_contents($filePath), true);
$repo = new App\CourseRepository();

/* Создание контейнера с установкой флеш-сообщений и рендера шаблонов */
$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
});

/* Маршрут формы создания пользователя */
$app->get('/users/new', function ($request, $response) {
    $params = ['user' => ['name' => '', 'password' => '', 'email' => '', 'id' => ''],
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('newUser');

/* Маршрут со списком пользователей, поиск пользователя по имени */
$app->get('/users', function ($request, $response) use ($usersRepo) {
    $term = $request->getQueryParam('term');
    $filteredUsers = collect($usersRepo)->filter(
        fn($user) => empty($term) ? true : s($user['name'])->ignoreCase()->startsWith($term)
    );
    $flash = $this->get('flash')->getMessages();
    $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $flash];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

/* Маршрут выдачи пользователя по id через передачу параметра запроса */
$app->get('/users/{id}', function ($request, $response, $args) use ($usersRepo) {
    $foundedUser = collect($usersRepo)->firstWhere('id', $args['id']);
    $flash = $this->get('flash')->getMessages();

    if (empty($foundedUser)) {
        return $response->write('User not exist')->withStatus(404);
    }
    $params = ['userInfo' => $foundedUser, 'flash' => $flash];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

/* Маршрут с регистрацией и валидацией нового пользователя  */
$app->post('/users', function ($request, $response) use ($repo, $filePath, $router) {
    $validator = new App\UserValidator(['passwordContainNumbers' => true]);
    $user = $request->getParsedBodyParam('user');
    $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
    $id = uniqid();
    $user['id'] = $id;
    $errors = $validator->validate($user);
    if (!empty($user)) {
        if (empty($errors)) {
            $users = json_decode(file_get_contents($filePath), true);
            $user['password'] = $hashedPassword;
            $users[$id] = $user;
            file_put_contents($filePath, json_encode($users));
            $this->get('flash')->addMessage('success', 'Registration successfully');
            $url = $router->urlFor('users');
            return $response->withRedirect($url, 302);
        }
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params)->withStatus(422);
});

/* Маршрут формы редактирования пользователельских данных */
$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($usersRepo) {
    $user = collect($usersRepo)->firstWhere('id', $args['id']);

    $params = [
        'user' => $user,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

/*Маршрут редактирования пользовательских данных */
$app->patch('/users/{id}', function ($request, $response, array $args) use ($usersRepo, $filePath, $router) {
    $users = json_decode(file_get_contents($filePath), true);
    $id = $args['id'];
    $user = collect($users)->firstWhere('id', $id);
    $data = $request->getParsedBodyParam('user');
    $user['name'] = $data['name'] ?? null;
    $validator = new App\UserValidator();
    $errors = $validator->validate($user);

    if (empty($errors)) {
        $users[$id] = $user;
        file_put_contents($filePath, json_encode($users));

        $this->get('flash')->addMessage('success', 'User has been updated');
        $url = $router->urlFor('user', ['id' => $user['id']]);
        return $response->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

/* Маршрут списка курсов */
$app->get('/courses', function ($request, $response) use ($repo) {
    $params = [
        'courses' => $repo->all()
    ];
    return $this->get('renderer')->render($response, 'courses/index.phtml', $params);
})->setName('courses');

/* Маршут формы создания курса */
$app->get('/courses/new', function ($request, $response) {
    $params = [
      'course' => ['title' => '', 'paid' => ''],
      'errors' => []
    ];
    return $this->get('renderer')->render($response, 'courses/new.phtml', $params);
})->setName('newCourse');

/* Маршрут создания курса с валидацией */
$app->post('/courses', function ($request, $response) use ($repo) {
    $validator = new App\CourseValidator(['mintLength' => 2]);
    $course = $request->getParsedBodyParam('course');
    $errors = $validator->validate($course);
    if (empty($errors)) {
        $repo->save($course);
        return $response->withRedirect('/courses', 302);
    }
    $params = ['course' => $course, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'courses/new.phtml', $params)->withStatus(422);
});

$app->run();