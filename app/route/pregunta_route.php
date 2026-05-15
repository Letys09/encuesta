<?php
	// use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	if(!function_exists('qrDownloadNameFromValor')) {
		function qrDownloadNameFromValor($valor, $codigo = null) {
			$fileSafe = trim((string)$valor);
			$translated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $fileSafe);
			if($translated !== false) {
				$fileSafe = $translated;
			}
			$fileSafe = strtolower($fileSafe);
			$fileSafe = preg_replace('/[^a-z0-9\-_]+/', '-', $fileSafe);
			$fileSafe = preg_replace('/-+/', '-', $fileSafe);
			$fileSafe = trim($fileSafe, '-');
			if($fileSafe === '') {
				$fileSafe = !is_null($codigo) ? 'valor-'.$codigo : 'valor';
			}

			return $fileSafe.'.png';
		}
	}

	$app->group('/pregunta/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de pregunta');
		});

		$this->get('getByPregunta/{pregunta}', function($request, $response, $arguments) {
			return $response->withJson($this->model->pregunta->getByPregunta($arguments['pregunta']));
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->pregunta->get($arguments['id']));
		});

		$this->get('getAll/[{pagina}/{limite}]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;

			return $response->withJson($this->model->pregunta->getAll($arguments['pagina'], $arguments['limite']));
		});

		$this->get('getAllDataTables/{inicial}/{limite}/{busqueda}[/{status}]', function($request, $response, $arguments) {
			$inicial = isset($_GET['start'])? $_GET['start']: $arguments['inicial'];
			$limite = isset($_GET['length'])? $_GET['length']: $arguments['limite']; $limite = $limite>0? $limite: 10;
			$busqueda = isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: '_'): $arguments['busqueda'];
			$orden = isset($_GET['order'])? $_GET['columns'][$_GET['order'][0]['column']]['data']: 'nombre';
			$orden .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";
			$status = isset($arguments['status'])? $arguments['status']: 0;
			
			if(count($_GET['order'])>1){
				for ($i=1; $i < count($_GET['order']); $i++) { 
					$orden .= ', '.$_GET['columns'][$_GET['order'][$i]['column']]['data'].' '.$_GET['order'][$i]['dir'];
				}
			}

			$preguntas = $this->model->pregunta->getAllDataTables($inicial, $limite, $busqueda, $orden, $status);
			$data = [];
			if(!isset($_SESSION)) { session_start(); }
			foreach($preguntas->result as $pregunta) {
				$acciones = $pregunta->status==1? '<a href="#" data-toogle="tooltip" data-placement="top" title="Ver encuestas" class="btnVerEncuestas"><i class="mdi mdi-eye" style="color: green;"></i></a> ': '';
				$acciones .= '<a href="#" data-toogle="tooltip" data-placement="top" title="Editar" class="btnEditPregunta"><i class="mdi mdi-pencil"></i></a> ';
				$acciones .= '<a href="#" data-toogle="tooltip" data-placement="top" title="Eliminar" class="btnDelPregunta"><i class="mdi mdi-delete" style="color:red;"></i></a>';

				$status = $pregunta->status == 1;
				$folio = $pregunta->ID_pregunta;
				$folio = "P-".(strlen($folio)<3? str_pad($folio, 3, '0', STR_PAD_LEFT): $folio);
				$opciones = str_replace(',', ', ', $pregunta->opciones);
				$data[] = array(
					"ID_pregunta" => "<small class=\"folio\">$folio</small>",
					"fecha" => "<small class=\"agregada\">".date('d/m/Y', strtotime($pregunta->fecha))."</small>",
					"pregunta" => "<small class=\"pregunta\">$pregunta->pregunta</small>",
					"tipo" => "<small class=\"tipo\">".($pregunta->tipo == 1 ? 'Abierta' : ($pregunta->tipo == 2 ? 'Opción múltiple' : ($pregunta->tipo == 3 ? 'Puntuación' : ($pregunta->tipo == 4 ? 'Postulación' : 'Votación'))))."</small>",
					"opciones" => "<small class=\"opciones\">".($pregunta->tipo == 2? $opciones: '-')."</small>",
					"status" => "<small class=\"status\"><span class=\"label label-".($status? 'success': 'warning')."\">".($status? 'Activo': 'Inactivo')."</span><a href=\"#\" class=\"btn".($status? 'Baja': 'Alta')." text-".($status? 'danger': 'success')." pull-right\" data-toogle=\"tooltip\" data-placement=\"top\" title=\"".($status? 'Inactivar': 'Activar')."\"><i class=\"mdi mdi-".($status? 'close': 'check')."-circle\"></i></a></small>",
					"acciones" => "<div class=\"pull-right acciones\">$acciones</div>",
					"acciones_encuesta" => "<div class=\"acciones pull-right\"><a href=\"#\" data-popup=\"tooltip\" title=\"Agregar\" class=\"btn btn-xs btn-success btnAddPregunta\"><i class=\"mdi fa-lg mdi-plus\"></i></a></div>",
					"data_id" => $pregunta->ID_pregunta,
				);
			}
			
			return $response->withJson([
				'draw' => $_GET['draw'],
				'data' => $data,
				'recordsTotal' => $preguntas->total,
				'recordsFiltered' => $preguntas->filtered,
			]);
		});

		$this->post('add/', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			if(!isset($parsedBody['fecha'])) { $parsedBody['fecha'] = date('Y-m-d\TH:i:s'); }
			if(!isset($parsedBody['status'])) { $parsedBody['status'] = 1; }
			if(!isset($parsedBody['tipo'])) { $parsedBody['tipo'] = 1; }
			if(!isset($parsedBody['opciones'])) { $parsedBody['opciones'] = ''; }
			if(!isset($parsedBody['escala'])) { $parsedBody['escala'] = ''; }
			if(!isset($parsedBody['icono'])) { $parsedBody['icono'] = ''; }

			if($parsedBody['tipo'] == 2 && (!isset($parsedBody['opciones']) || strlen($parsedBody['opciones'])==0)) {
				return $response->withJson(['success'=>false, 'message'=>'Debe ingresar las opciones para este tipo de pregunta']);
			}

			if($parsedBody['tipo'] == 3 && (!isset($parsedBody['escala']) || strlen($parsedBody['escala']) == 0)) {
				return $response->withJson(['success'=>false, 'message'=>'Debe seleccionar la escala para este tipo de pregunta']);
			}

			if($parsedBody['tipo'] == 3 && (!isset($parsedBody['icono']) || strlen($parsedBody['icono']) == 0)) {
				return $response->withJson(['success'=>false, 'message'=>'Debe seleccionar el icono para este tipo de pregunta']);
			}

			if($parsedBody['tipo'] == 2){
				$opciones = explode(",", $parsedBody['opciones']);
				foreach($opciones as &$opcion) {
					$opcion = trim($opcion);
					$opcion = mb_strtoupper(mb_substr($opcion, 0, 1), 'UTF-8') . mb_substr($opcion, 1, null, 'UTF-8');
				}
				unset($opcion);

				$parsedBody['opciones'] = implode(",", $opciones);
			} else {
				$parsedBody['opciones'] = '';
			}

			if($parsedBody['tipo'] != 3) {
				$parsedBody['escala'] = '';
				$parsedBody['icono'] = null;
			}

			return $response->withHeader('Content-type', 'application/json')->write(json_encode($this->model->pregunta->add($parsedBody)));
		});

		$this->post('edit/{id}', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();

			if(isset($parsedBody['tipo'])) {
				if($parsedBody['tipo'] != 3) {
					$parsedBody['escala'] = '';
					$parsedBody['icono'] = null;
				}

				if($parsedBody['tipo'] != 2) {
					$parsedBody['opciones'] = '';
				}
			}

			return $response->withJson($this->model->pregunta->edit($parsedBody, $arguments['id']));
		});

		$this->post('del/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->pregunta->del($arguments['id']));
		});

		$this->get('qr/values', function($request, $response, $arguments) {
			$baseRoot = rtrim(URL_ROOT, '/');
			$baseApi = rtrim(URL_API, '/');
			$values = $this->model->valor_qr->getAll();

			$result = [];
			foreach($values->result as $row) {
				$result[] = [
					'id' => $row->id,
					'codigo' => $row->codigo,
					'nombre' => $row->valor,
					'scan_url' => $baseRoot.'/'.$row->codigo,
					'image_url' => $baseApi.'/pregunta/qr/image/'.$row->codigo,
					'download_name' => qrDownloadNameFromValor($row->valor, $row->codigo)
				];
			}

			return $response->withJson([
				'response' => true,
				'count' => count($result),
				'result' => $result,
			]);
		});

		$this->post('qr/sync', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			$rawValues = [];
			if(isset($parsedBody['values_json'])) {
				$rawValues = json_decode($parsedBody['values_json'], true);
			} elseif(isset($parsedBody['values']) && is_array($parsedBody['values'])) {
				$rawValues = $parsedBody['values'];
			}

			if(!is_array($rawValues)) {
				$rawValues = [];
			}

			$values = [];
			$unique = [];
			foreach($rawValues as $value) {
				$item = trim((string)$value);
				if($item === '') {
					continue;
				}

				$key = mb_strtolower($item, 'UTF-8');
				if(isset($unique[$key])) {
					continue;
				}

				$unique[$key] = true;
				$values[] = $item;
			}

			if(count($values) === 0) {
				return $response->withJson([
					'response' => false,
					'message' => 'Ingresa al menos un valor para generar su QR'
				]);
			}

			if(count($values) > 14) {
				return $response->withJson([
					'response' => false,
					'message' => 'Solo puedes guardar hasta 14 valores'
				]);
			}

			$saved = $this->model->valor_qr->replaceAll($values);
			if(!$saved->response) {
				return $response->withJson([
					'response' => false,
					'message' => $saved->message
				]);
			}

			$result = [];
			foreach($saved->result as $row) {
				$result[] = [
					'codigo' => $row->codigo,
					'nombre' => $row->valor,
					'scan_url' => URL_ROOT.'/valor/'.$row->codigo,
					'image_url' => URL_ROOT.'/pregunta/qr/image/'.$row->codigo,
					'download_name' => qrDownloadNameFromValor($row->valor, $row->codigo)
				];
			}

			return $response->withJson([
				'response' => true,
				'count' => count($result),
				'result' => $result,
			]);
		});

		$this->get('qr/image/{codigo:[0-9]{2}}', function($request, $response, $arguments) {
			$codigo = trim($arguments['codigo']);
			$valueResult = $this->model->valor_qr->getByCodigo($codigo);
			$valor = ($valueResult->response && $valueResult->result) ? trim($valueResult->result->valor) : '';
			if($valor === '') {
				return $response->withStatus(404)->write('Codigo no encontrado');
			}

			if(!extension_loaded('gd')) {
				return $response->withStatus(500)->write('La extension GD no esta disponible');
			}

			if(!class_exists('QRcode')) {
				require_once __DIR__.'/../../vendor/tecnickcom/tcpdf/include/barcodes/qrcode.php';
			}

			$scanUrl = rtrim(URL_ROOT, '/').'/valor/'.md5($codigo);
			$qr = new \QRcode($scanUrl, 'M');
			$matrix = $qr->getBarcodeArray();
			if(!isset($matrix['bcode']) || count($matrix['bcode']) === 0) {
				return $response->withStatus(500)->write('No fue posible generar el QR');
			}

			$moduleSize = 8;
			$quietZone = 2;
			$rows = $matrix['num_rows'];
			$cols = $matrix['num_cols'];
			$imgWidth = ($cols + ($quietZone * 2)) * $moduleSize;
			$imgHeight = ($rows + ($quietZone * 2)) * $moduleSize;

			$image = imagecreatetruecolor($imgWidth, $imgHeight);
			$white = imagecolorallocate($image, 255, 255, 255);
			$black = imagecolorallocate($image, 0, 0, 0);
			imagefill($image, 0, 0, $white);

			for($y = 0; $y < $rows; $y++) {
				for($x = 0; $x < $cols; $x++) {
					if((int)$matrix['bcode'][$y][$x] === 1) {
						$startX = ($x + $quietZone) * $moduleSize;
						$startY = ($y + $quietZone) * $moduleSize;
						imagefilledrectangle(
							$image,
							$startX,
							$startY,
							$startX + $moduleSize - 1,
							$startY + $moduleSize - 1,
							$black
						);
					}
				}
			}

			ob_start();
			imagepng($image);
			$pngData = ob_get_clean();
			imagedestroy($image);

			$response->getBody()->write($pngData);
			$downloadName = qrDownloadNameFromValor($valor, $codigo);
			return $response
				->withHeader('Content-Type', 'image/png')
				->withHeader('Content-Disposition', 'inline; filename="'.$downloadName.'"')
				->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
		});

		$this->post('qr/delete/{id}', function($request, $response, $arguments) {
			$id = trim($arguments['id']);
			$valueResult = $this->model->valor_qr->getById($id);
			if(!$valueResult->response || !$valueResult->result) {
				return $response->withStatus(404)->withJson(['response'=>false, 'message'=>'Codigo no encontrado']);
			}

			$deleted = $this->model->valor_qr->delById($id);
			if(!$deleted->response) {
				return $response->withStatus(500)->withJson(['response'=>false, 'message'=>'No fue posible eliminar el codigo']);
			}

			return $response->withJson(['response'=>true, 'message'=>'Codigo eliminado']);
		});

		$this->get('getRegistro/{invitado_id}', function($request, $response, $arguments) {
			$registroResult = $this->model->valor_qr->getRegistro($arguments['invitado_id']);
			if(!$registroResult->response) {
				return $response->withJson(['response' => false]);
			}

			return $response->withJson(['response' => true]);
		});

		$this->get('export/{format}/{busqueda}', function($request, $response, $arguments) {
			$preguntas = $this->model->pregunta->getAll(0, 0, $arguments['busqueda'])->result;
			if($arguments['format'] == 'xlsx') {
				return $this->rpt_renderer->render($response, 'xlsxPreguntas.phtml', ['preguntas'=>$preguntas]);
			} elseif($arguments['format'] == 'pdf') {
				return $this->rpt_renderer->render($response, 'pdfPreguntas.phtml', ['preguntas'=>$preguntas, 'vista'=>'rpt preguntas']);
			}
		});
	})/* ->add( new MiddlewareToken() ) */;
?>