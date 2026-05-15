<?php
	use App\Lib\Response;
	// use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	$app->group('/universo/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de universo');
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->universo->get($arguments['id']));
		});

		$this->get('getByEncuesta/{ID_encuesta}[/{ID_pregunta}]', function($request, $response, $arguments) {
			$arguments['ID_pregunta'] = isset($arguments['ID_pregunta'])? $arguments['ID_pregunta']: 0;

			return $response->withJson($this->model->universo->getByEncuesta($arguments['ID_encuesta'], $arguments['ID_pregunta']));
		});

		$this->get('getByPregunta/{ID_pregunta}[/{status}]', function($request, $response, $arguments) {
			$arguments['status'] = isset($arguments['status'])? $arguments['status']: 0;

			return $response->withJson($this->model->universo->getByPregunta($arguments['ID_pregunta'], $arguments['status']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->universo->add($request->getParsedBody()));
		});

		$this->post('del/{ID_universo}', function($request, $response, $arguments) {
			return $response->withJson($this->model->universo->del($arguments['ID_universo']));
		});

		$this->post('delByEncuesta/{ID_encuesta}[/{ID_pregunta}]', function($request, $response, $arguments) {
			$arguments['ID_pregunta'] = isset($arguments['ID_pregunta'])? $arguments['ID_pregunta']: 0;

			return $response->withJson($this->model->universo->delByEncuesta($arguments['ID_encuesta'], $arguments['ID_pregunta']));
		});
	})/* ->add( new MiddlewareToken() ) */;
?>