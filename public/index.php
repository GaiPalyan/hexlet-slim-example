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

/* imitation databases */
$filePath = '..' . DIRECTORY_SEPARATOR . 'DB' . DIRECTORY_SEPARATOR . 'userData.json';
try {
    $usersRepo = json_decode(file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
}
$repo = new App\CourseRepository();
/* Create a container with flash messages and render templates */
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
    $message = $this->get('flash')->getMessages();
    $params = [
        'user' => ['name' => '', 'password' => '', 'email' => '', 'id' => ''],
        'session' => $_SESSION,
        'flash' => $message
        ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
});

/* User-create form renderer */
$app->get('/users/new', function ($request, $response) {
    $params = ['user' => ['name' => '', 'password' => '', 'email' => '', 'id' => ''],
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('newUser');

/* Route user list, search user by name */
$app->get('/users', function ($request, $response) use ($usersRepo) {
    $term = $request->getQueryParam('term');

    $filteredUsers = collect($usersRepo)
        ->merge(json_decode($request
            ->getCookieParam('users', json_encode([])), true, 512, JSON_THROW_ON_ERROR))
        ->filter(
            fn($user) => empty($term) ? true : s($user['name'])->ignoreCase()->startsWith($term)
        );
    $flash = $this->get('flash')->getMessages();
    $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $flash];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

/* Route for getting user by id */
$app->get('/users/{id}', function ($request, $response, $args) use ($usersRepo) {
    $foundedUser = collect($usersRepo)
        ->merge(json_decode($request
            ->getCookieParam('users', json_encode([])), true, 512, JSON_THROW_ON_ERROR))
        ->firstWhere('id', $args['id']);
    $flash = $this->get('flash')->getMessages();
    if (empty($foundedUser)) {
        return $response->write('User not exist')->withStatus(404);
    }
    $params = ['userInfo' => $foundedUser, 'flash' => $flash];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

/* Registration and validation route  */
$app->post('/users', function ($request, $response) use ($usersRepo, $filePath, $router) {
    $validator = new App\UserValidator(['passwordContainNumbers' => true]);
    $user = $request->getParsedBodyParam('user');
    $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
    $id = uniqid('', true);
    $user['id'] = $id;
    $errors = $validator->validate($user);
    if (!empty($user) && empty($errors)) {
        $cookedUsers = json_decode($request
            ->getCookieParam('users', json_encode([])), true, 512, JSON_THROW_ON_ERROR);
        $users = collect($usersRepo)->merge($cookedUsers);
        $user['password'] = $hashedPassword;
        $users[$id] = $user;
        $encodedUsers = json_encode($users, JSON_THROW_ON_ERROR);
        file_put_contents($filePath, $encodedUsers);
        $this->get('flash')->addMessage('success', 'Registration successfully');
        $url = $router->urlFor('users');
        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect($url, 302);
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'users/new.phtml', $params)->withStatus(422);
});

/* User delete route */
$app->delete('/users/{id}', function ($request, $response, array $args) use ($usersRepo, $filePath, $router) {
    $users = collect($usersRepo);
    $id = $args['id'];
    unset($users[$id]);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    file_put_contents($filePath, json_encode($users));
    return $response->withRedirect($router->urlFor('users'));
});

/* Edit form renderer */
$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($usersRepo) {
    $user = collect($usersRepo)->firstWhere('id', $args['id']);

    $params = [
        'user' => $user,
        'errors' => [],
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

/* User edit route */
$app->patch('/users/{id}', function ($request, $response, array $args) use ($usersRepo, $filePath, $router) {
    $id = $args['id'];
    $user = collect($usersRepo)
        ->merge(json_decode($request->getCookieParam('users', json_encode([])), true))
        ->firstWhere('id', $id);
    $data = $request->getParsedBodyParam('user');
    $user['name'] = $data['name'] ?? null;
    $validator = new App\UserValidator();
    $errors = $validator->validate($user);
    if (empty($errors)) {
        $usersRepo[$id] = $user;
        $encodedUsers = json_encode($usersRepo);
        file_put_contents($filePath, $encodedUsers);

        $this->get('flash')->addMessage('success', 'User has been updated');
        $url = $router->urlFor('user', ['id' => $user['id']]);
        return $response->withHeader('Set-Cookie', "users={$encodedUsers}")->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});
/* Authentication  */
$app->post('/session', function ($request, $response) use ($usersRepo) {
    $data = $request->getParsedBodyParam('user');
    $password = $data['password'];

    $matchedUser = collect($usersRepo)->firstWhere('email', $data['email']);

    $authentication = function ($userName) use ($password, $matchedUser) {
        $verified = password_verify($password, $matchedUser['password']) ?
            $_SESSION['user']['id'] = $matchedUser['id'] : false;
        return $verified ? $_SESSION['user']['name'] = $userName : $this->get('flash')
            ->addMessage('false', 'Wrong password or email');
    };

    if (!empty($matchedUser)) {
         $authentication($matchedUser['name']);
    } else {
        $this->get('flash')->addMessage('false', 'Wrong password or email');
    }
    return $response->withRedirect('/');
});
/* Log out */
$app->delete('/session', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect('/');
});




/* Course list route */
$app->get('/courses', function ($request, $response) use ($repo) {
    $params = [
        'courses' => $repo->all()
    ];
    return $this->get('renderer')->render($response, 'courses/index.phtml', $params);
})->setName('courses');

/* Create-course form renderer */
$app->get('/courses/new', function ($request, $response) {
    $params = [
      'course' => ['title' => '', 'paid' => ''],
      'errors' => []
    ];
    return $this->get('renderer')->render($response, 'courses/new.phtml', $params);
})->setName('newCourse');


/* Create and validate course route */
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
