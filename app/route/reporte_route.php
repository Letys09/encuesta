<?php
	require_once './core/defines.php';

	$app->group('/reporte/', function() {
		$this->get('dashboard', function($request, $response, $arguments) {
			return $response->withJson($this->model->reporte->getDashboardStats());
		});

		$this->get('resultados/{ID_encuesta}', function($request, $response, $arguments) {
			$params     = $request->getQueryParams();
			$fecha_desde = isset($params['desde']) ? trim($params['desde']) : '';
			$fecha_hasta = isset($params['hasta']) ? trim($params['hasta']) : '';
			return $response->withJson($this->model->reporte->getResultadosByEncuesta($arguments['ID_encuesta'], $fecha_desde, $fecha_hasta));
		});

		$this->get('export/{format}/{ID_encuesta}', function($request, $response, $arguments) {
			$params      = $request->getQueryParams();
			$fecha_desde = isset($params['desde']) ? trim($params['desde']) : '';
			$fecha_hasta = isset($params['hasta']) ? trim($params['hasta']) : '';
			$data        = $this->model->reporte->getRespuestasExport($arguments['ID_encuesta'], $fecha_desde, $fecha_hasta);

			if (!$data->response) {
				return $response->withStatus(404)->write($data->message);
			}

			if ($arguments['format'] === 'xlsx') {
				return $this->rpt_renderer->render($response, 'xlsxRespuestas.phtml', [
					'encuesta'  => $data->result['encuesta'],
					'preguntas' => $data->result['preguntas'],
					'filas'     => $data->result['filas'],
					'desde'     => $data->result['desde'],
					'hasta'     => $data->result['hasta'],
				]);
			} elseif ($arguments['format'] === 'pdf') {
				return $this->rpt_renderer->render($response, 'pdfRespuestas.phtml', [
					'encuesta'  => $data->result['encuesta'],
					'preguntas' => $data->result['preguntas'],
					'filas'     => $data->result['filas'],
					'desde'     => $data->result['desde'],
					'hasta'     => $data->result['hasta'],
					'vista'     => 'Respuestas',
				]);
			}

			return $response->withStatus(400)->write('Formato no válido');
		});

		$this->get('valores-votos', function($request, $response, $arguments) {
			$params      = $request->getQueryParams();
			$fecha_desde = isset($params['desde']) ? trim($params['desde']) : '';
			$fecha_hasta = isset($params['hasta']) ? trim($params['hasta']) : '';
			return $response->withJson($this->model->reporte->getValoresVotos($fecha_desde, $fecha_hasta));
		});

		$this->get('valores-votos/export/{format}', function($request, $response, $arguments) {
			$params      = $request->getQueryParams();
			$fecha_desde = isset($params['desde']) ? trim($params['desde']) : '';
			$fecha_hasta = isset($params['hasta']) ? trim($params['hasta']) : '';
			$data        = $this->model->reporte->getValoresVotos($fecha_desde, $fecha_hasta);

			if (!$data->response) {
				return $response->withStatus(404)->write($data->message);
			}

			if ($arguments['format'] === 'xlsx') {
				return $this->rpt_renderer->render($response, 'xlsxValoresVotos.phtml', [
					'valores' => $data->result['valores'],
					'total_votos' => $data->result['total_votos'],
					'desde' => $data->result['desde'],
					'hasta' => $data->result['hasta'],
				]);
			} elseif ($arguments['format'] === 'pdf') {
				return $this->rpt_renderer->render($response, 'pdfValoresVotos.phtml', [
					'valores' => $data->result['valores'],
					'total_votos' => $data->result['total_votos'],
					'desde' => $data->result['desde'],
					'hasta' => $data->result['hasta'],
					'vista' => 'Valores y votos',
				]);
			}

			return $response->withStatus(400)->write('Formato no válido');
		});

		$this->get('postulados/{ID_encuesta}/{ID_pregunta}', function($request, $response, $arguments) {
			return $response->withJson($this->model->reporte->getPostulados($arguments['ID_encuesta'], $arguments['ID_pregunta']));
		});

		$this->get('finalistas/votacion-status/{ID_encuesta}/{ID_pregunta}', function($request, $response, $arguments) {
			return $response->withJson($this->model->reporte->getVotingQuestionStatus($arguments['ID_encuesta'], $arguments['ID_pregunta']));
		});

		$this->post('finalistas/save/{ID_encuesta}/{ID_pregunta}', function($request, $response, $arguments) {
			$body      = $request->getParsedBody();
			$rawIds    = isset($body['invitados']) ? $body['invitados'] : [];
			$invitados = is_array($rawIds) ? $rawIds : [];
			return $response->withJson($this->model->reporte->saveFinalists($arguments['ID_encuesta'], $arguments['ID_pregunta'], $invitados));
		});

		$this->post('finalistas/create-votacion/{ID_encuesta}/{ID_pregunta}', function($request, $response, $arguments) {
			$body = $request->getParsedBody();
			$pregunta = isset($body['pregunta']) ? trim($body['pregunta']) : '';
			return $response->withJson($this->model->reporte->createVotingQuestion($arguments['ID_encuesta'], $arguments['ID_pregunta'], $pregunta));
		});
	});
?>
