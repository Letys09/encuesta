<?php
	use App\Lib\Response;
	// use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	$app->group('/intento/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de intento');
		})/* ->add( new MiddlewareToken() ) */;

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->intento->get($arguments['id']));
		})/* ->add( new MiddlewareToken() ) */;

		$this->get('getAll/[{pagina}/{limite}]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;

			return $response->withJson($this->model->intento->getAll($arguments['pagina'], $arguments['limite']));
		})/* ->add( new MiddlewareToken() ) */;

		$this->get('exists/{ID_url}/{invitado_id}', function($request, $response, $arguments) {
			$exists = $this->model->intento->existsByUrlInvitado($arguments['ID_url'], $arguments['invitado_id']);
			return $response->withJson(['response' => $exists->response]);
		})/* ->add( new MiddlewareToken() ) */;

		$this->post('add/', function($request, $response, $arguments) {
			$this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$invitado_id = isset($parsedBody['invitado_id']) ? trim($parsedBody['invitado_id']) : '';
			if($invitado_id === '') {
				$res = new Response();
				$res->SetResponse(false, 'Debes ingresar el numero de visitante.');
				$this->model->transaction->regresaTransaccion();
				return $response->withJson($res);
			}

			$exists = $this->model->intento->existsByUrlInvitado($parsedBody['ID_url'], $invitado_id);
			if($exists->response) {
				$res = new Response();
				$res->SetResponse(false, 'Este numero de visitante ya respondio esta encuesta.');
				$this->model->transaction->regresaTransaccion();
				return $response->withJson($res);
			}

			$dataIntento = [ 
				'ID_url' => $parsedBody['ID_url'], 
				'invitado_id' => $invitado_id,
				'correo' => $parsedBody['correo'], 
				'inicio' => $parsedBody['inicio'], 
				'final' => $parsedBody['final'], 
				'comentarios' => $parsedBody['comentarios'] 
			];
			$intento = $this->model->intento->add($dataIntento); 
			if($intento->response) { $ID_intento = $intento->result;
				$dataRespuesta = [ 'ID_intento' => $ID_intento ];
				foreach($parsedBody['respuestas'] as $dataRespuesta) {
					$dataRespuesta['ID_intento'] = $ID_intento;
					$respuesta = $this->model->respuesta->add($dataRespuesta); if(!$respuesta->response) {
						$respuesta->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($respuesta); 
					}
				}
			} else { $intento->state = $this->model->transaction->regresaTransaccion(); return $response->withJson($intento); }

			unset($_SESSION['encuestas'][$parsedBody['code']]);
			$intento->state = $this->model->transaction->confirmaTransaccion();
			return $response->withJson($intento);
		});
	});
?>