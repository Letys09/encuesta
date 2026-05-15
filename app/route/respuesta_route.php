<?php
	use App\Lib\Response;
	// use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	$app->group('/respuesta/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de respuesta');
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->respuesta->get($arguments['id']));
		});

		$this->get('getByIntento/{ID_encuesta}[/{ID_pregunta}]', function($request, $response, $arguments) {
			$arguments['ID_pregunta'] = isset($arguments['ID_pregunta'])? $arguments['ID_pregunta']: 0;

			return $response->withJson($this->model->respuesta->getByIntento($arguments['ID_encuesta'], $arguments['ID_pregunta']));
		});

		$this->get('getByPregunta/{ID_pregunta}', function($request, $response, $arguments) {
			return $response->withJson($this->model->respuesta->getByPregunta($arguments['ID_pregunta']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			return $response->withJson($this->model->respuesta->add($request->getParsedBody()));
		});

		$this->post('addResp/', function($request, $response, $arguments) {
			return $response->withJson($this->model->respuesta->addResp($request->getParsedBody()));
		});
	})/* ->add( new MiddlewareToken() ) */;
?>