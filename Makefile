start:
	php -S localhost:8080 -t public public/index.php
lint:
	composer run-script phpcs -- --standard=PSR12 src public
lint-fix:
	composer exec --verbose phpcbf -- --standard=PSR12 src public