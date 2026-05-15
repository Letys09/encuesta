<?php
	use App\Lib\Response;
	// use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	$app->group('/usuario/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de usuario');
		});

		$this->post('login/', function($request, $response, $arguments) {
			$parsedBody= $request->getParsedBody();
			$email = $parsedBody['email'];
			$contrasena = $parsedBody['contrasena'];

			$usuario = $this->model->usuario->login($email, $contrasena);
			if($usuario->response) {
				/* $_SESSION['token'] = $this->model->usuario->crearToken($usuario->result);
				$fecha_cambio = $usuario->result->fecha_cambio;
				
				$fecha_cambio = new DateTime($fecha_cambio);
				$today = new DateTime();
	
				$_SESSION['diferencia'] = $fecha_cambio->diff($today)->days; */

				$_SESSION['diferencia'] = 0;
				$this->logger->info("Slim-Skeleton 'usuario/login/' ".$usuario->result->id);
			}

			return $response->withJson($usuario);
		});

		$this->get('logout', function($request, $response, $arguments) use ($app) {
			$userId = $_SESSION['usuario']->id_login;
			$this->logger->info("Slim-Skeleton 'usuario/logout/' $userId");
			$this->model->usuario->logout();

			return $this->response->withRedirect('../login');
		});

		$this->get('validar/{password}', function($req, $res, $args){
			return $res->withJson($this->model->usuario->validar($args['password']));
		});

		$this->post('change/', function($req, $res, $args){
			error_reporting(0);
			$parsedBody = $req->getParsedBody();
			$parsedBody['fecha_cambio'] = date('Y-m-d');
			$parsedBody['Password_portal'] = $parsedBody['password'];

			$cambio = $this->model->usuario->change($parsedBody);
			if( $cambio->response ) $_SESSION['diferencia'] = 0;
			return $res->withJson($cambio);
		});
	});
?>