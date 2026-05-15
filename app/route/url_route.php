<?php
	use App\Lib\Response;
	// use App\Lib\MiddlewareToken;
	require_once './core/defines.php';

	$app->group('/url/', function() use($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de url');
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->url->get($arguments['id']));
		});

		$this->get('getAll/[{pagina}/{limite}]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;

			return $response->withJson($this->model->url->getAll($arguments['pagina'], $arguments['limite']));
		});

		$this->get('getByEncuesta/{ID_encuesta}[/{status}]', function($request, $response, $arguments) {
			$arguments['status'] = isset($arguments['status'])? $arguments['status']: 0;

			return $response->withJson($this->model->url->getByEncuesta($arguments['ID_encuesta'], $arguments['status']));
		});

		$this->get('getAllDataTables/{inicial}/{limite}/{busqueda}', function($request, $response, $arguments) {
			$inicial = isset($_GET['start'])? $_GET['start']: $arguments['inicial'];
			$limite = isset($_GET['length'])? $_GET['length']: $arguments['limite']; $limite = $limite>0? $limite: 10;
			$busqueda = isset($_GET['search']['value'])? (strlen($_GET['search']['value'])>0? $_GET['search']['value']: '_'): $arguments['busqueda'];
			$orden = isset($_GET['order'])? $_GET['columns'][$_GET['order'][0]['column']]['data']: 'nombre';
			$orden .= isset($_GET['order'])? " ".$_GET['order'][0]['dir']: " asc";

			if(count($_GET['order'])>1){
				for ($i=1; $i < count($_GET['order']); $i++) { 
					$orden .= ', '.$_GET['columns'][$_GET['order'][$i]['column']]['data'].' '.$_GET['order'][$i]['dir'];
				}
			}

			$urls = $this->model->url->getAllDataTables($inicial, $limite, $busqueda, $orden);

			$data = [];
			if(!isset($_SESSION)) { session_start(); }
			foreach($urls->result as $url) {
				$acciones = '<a href="#" data-toogle="tooltip" data-placement="top" title="Eliminar" class="btnDelUrl"><i class="mdi mdi-delete" style="color:red;"></i></a>';

				$status = $url->status == 1;
				$folio = $url->ID_url;
				$folio = "U-".(strlen($folio)<3? str_pad($folio, 3, '0', STR_PAD_LEFT): $folio);
				$data[] = array(
					"ID_url" => "<small class=\"folio\">$folio</small>",
					"fecha" => "<small class=\"creacion\">".date('d/m/Y', strtotime($url->fecha))."</small>",
					"nombre" => "<small class=\"encuesta\" data-id=\"$url->ID_encuesta\">".mb_strtoupper($url->nombre)."</small>",
					"url" => "<small class=\"url\"><a href=\"$url->urltxt\" target=\"_BLANK\">$url->urltxt</a></small>",


					//"url" => "<small class=\"url\"><a href=\"".URL_ROOT."/enc/'$url->urltxt\" target=\"_BLANK\">".URL_ROOT."/enc/$url->urltxt</a></small>",

					"shortUrl" => URL_ROOT."/enc/$url->url",
					"num_preguntas" => "<small class=\"preguntas\">$url->num_preguntas</small>",
					"num_intentos" => "<small class=\"respondido\">$url->num_intentos</small>",
					"status" => "<small class=\"status\"><span class=\"label label-".($status? 'success': 'warning')."\">".($status? 'Activo': 'Inactivo')."</span><a href=\"#\" class=\"btn".($status? 'Baja': 'Alta')." text-".($status? 'danger': 'success')." pull-right\" data-toogle=\"tooltip\" data-placement=\"top\" title=\"".($status? 'Inactivar': 'Activar')."\"><i class=\"mdi mdi-".($status? 'close': 'check')."-circle\"></i></a></small>",
					"acciones" => "<div class=\"pull-right acciones\">$acciones</div>",
					"data_id" => $url->ID_url,
				);
			}

			echo json_encode(array(
				'draw' => $_GET['draw'],
				'data' => $data,
				'recordsTotal' => $urls->total,
				'recordsFiltered' => $urls->filtered,
			));
			exit(0);
		});


		$this->post('addURL/', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			//if(!isset($parsedBody['fecha'])) { $parsedBody['fecha'] = date('Y-m-d\TH:i:s'); }
			//if(!isset($parsedBody['status'])) { $parsedBody['status'] = 1; }

			$url = $this->model->url->updateURL($parsedBody->ID_url);
			//$guardourl = $this->model->url->updateURL($data->ID_url);

			return $response->withJson($url);
		});




		$this->post('add/', function($request, $response, $arguments) {
			$parsedBody = $request->getParsedBody();
			if(!isset($parsedBody['fecha'])) { $parsedBody['fecha'] = date('Y-m-d\TH:i:s'); }
			if(!isset($parsedBody['status'])) { $parsedBody['status'] = 1; }

			$url = $this->model->url->add($parsedBody);
			$data = $this->model->url->get($url->result)->result;
			$data->encuesta = $this->model->encuesta->get($data->ID_encuesta)->result;
			$data->url = base64_encode((binary)$data->ID_url."_".(binary)$data->fecha);
			$urltxt = "https://trasladosuniversales.com.mx/app/encuestas/public/enc/'".$data->url;
			$data->intentos = [];
			$url->data = $data;
			$guardourl = $this->model->url->updateURL($data->ID_url, $urltxt);

			return $response->withJson($guardourl);
			//return $response->withJson($url);
		});

		$this->post('edit/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->url->edit($request->getParsedBody(), $arguments['id']));
		});

		$this->post('del/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->url->del($arguments['id']));
		});

		$this->get('export/{format}/{busqueda}', function($request, $response, $arguments) {
			$urls = $this->model->url->getAll(0, 0, $arguments['busqueda'])->result;
			if($arguments['format'] == 'xlsx') {
				return $this->rpt_renderer->render($response, 'xlsxUrls.phtml', ['urls'=>$urls]);
			} elseif($arguments['format'] == 'pdf') {
				return $this->rpt_renderer->render($response, 'pdfUrls.phtml', ['urls'=>$urls, 'vista'=>'rpt enlaces']);
			}
		});

		$this->post('sendEmail/', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();
			$emailList = explode(',', $parsedBody['emailList']);
			$asunto = $parsedBody['asunto'];
			$mensaje = $parsedBody['mensaje'];
			$enlace = $parsedBody['enlace'];
			$encuesta = $parsedBody['encuesta'];

			$body = 
				"<!DOCTYPE html>
				<html lang=\"en\" xmlns=\"http://www.w3.org/1999/xhtml\">
					<head>
						<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
						<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
						<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
						<meta name=\"format-detection\" content=\"date=no\">
						<meta name=\"format-detection\" content=\"address=no\">
						<meta name=\"format-detection\" content=\"telephone=no\">
						<title>". SITE_NAME ."</title>
						<link href=\"https://fonts.googleapis.com/css?family=Poppins:400,500,600,700&display=swap\" rel=\"stylesheet\">
						<style>
						*{ -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
						body { padding: 0 !important; margin: 0 !important; display: block !important; min-width: 100% !important; width: 100% !important; background: #e2231a; font-family: 'Poppins', Helvetica Neue, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: none; }
						a { color: #B3B2B1; text-decoration: underline; }
						p { padding: 0 !important; margin: 0 !important; }
						strong { font-weight: 500 !important; }
						img { -ms-interpolation-mode: bicubic; }
						@media only screen and (max-width:525px) {
							.mobile-br-15 { height: 15px !important; }
							.m-td, .m-td, .m-td{ display: none !important; width: 0 !important; height: 0 !important; font-size: 0 !important; line-height: 0 !important; min-height: 0 !important; }
							.img-m-center{ text-align: center !important; }
							.text-r-m-center, .text-white-top, .text-white-top{ text-align: center !important; }
							.fluid-img img, .fluid-img img { width: 100% !important; max-width: 100% !important; height: auto !important; }
							.mobile-shell { width: 100% !important; min-width: 100% !important; }
							.center { margin: 0 auto; }
							.td{ width: 100% !important; min-width: 100% !important; }
							.content-spacing { width: 15px !important; }
						}
						</style>
					</head>
					<body class=\"body\" style=\"padding:0 !important; margin:0 !important; display:block !important; min-width:100% !important; width:100% !important; background:#e2231a; -webkit-text-size-adjust:none;\">
						<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#ededed\">
							<tr>
								<td align=\"center\" valign=\"top\">
									<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#E2231A\">
										<tr>
											<td align=\"center\">
												<table width=\"650\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"mobile-shell\">
													<tr>
														<td class=\"td\" style=\"width:650px; min-width:650px; font-size:0pt; line-height:0pt; padding:0; margin:0; font-weight:normal;\">
															<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
																<tr>
																	<td>
																		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																			<tr>
																				<td height=\"30\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																			</tr>
																		</table>
																		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																			<tr>
																				<td height=\"25\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																			</tr>
																		</table>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
									<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#ededed\">
										<tr>
											<td valign=\"top\" class=\"m-td\" style=\"font-size:0pt; line-height:0pt; text-align:left\">
												<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#E2231A\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
													<tr>
														<td bgcolor=\"#E2231A\" height=\"190\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
													</tr>
												</table>
											</td>
											<td width=\"650\" align=\"center\">
												<table width=\"650\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"mobile-shell\" bgcolor=\"#ffffff\">
													<tr>
														<td class=\"td\" style=\"width:650px; min-width:650px; font-size:0pt; line-height:0pt; padding:0; margin:0; font-weight:normal;\">
															<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
																<tr>
																	<td class=\"img\" style=\"font-size:0pt; line-height:0pt; text-align:left\" width=\"10\"></td>
																	<td>
																		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																			<tr>
																				<td height=\"50\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																			</tr>
																		</table>
																		<div style=\"font-size:0pt; line-height:0pt;\">
																			<div class=\"img-center\" style=\"font-size:0pt; line-height:0pt; text-align:center\">
																				<img src=\"". URL_ROOT ."/assets/images/Logo_ATM.png\" border=\"0\" width=\"300\" height=\"190\" alt=\"\" />
																			</div>
																		</div>
																		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																			<tr>
																				<td height=\"40\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																			</tr>
																		</table>
																	</td>
																	<td class=\"img\" style=\"font-size:0pt; line-height:0pt; text-align:left\" width=\"10\"></td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
												<table width=\"650\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"mobile-shell\">
													<tr>
														<td>
															<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#ffffff\">
																<tr>
																	<td class=\"content-spacing\" style=\"font-size:0pt; line-height:0pt; text-align:left\" width=\"30\"></td>
																	<td>
																		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																			<tr>
																				<td height=\"40\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																			</tr>
																		</table>
																		<div style=\"color:#555555; font-family: 'Poppins', Helvetica Neue, Helvetica, Arial, sans-serif; font-size:14px; line-height:21px; text-align:left\">
																			$mensaje<br><br>
																			Puedes acceder a la encuesta a través del siguiente enlace: <a href=\"$enlace\">$encuesta</a>
																		</div>
																		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																			<tr>
																				<td height=\"40\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																			</tr>
																		</table>
																	</td>
																	<td class=\"content-spacing\" style=\"font-size:0pt; line-height:0pt; text-align:left\" width=\"30\"></td>
																</tr>
															</table>
														</td>
													</tr>
													<tr>
														<td>
															<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																<tr>
																	<td height=\"20\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
											<td valign=\"top\" class=\"m-td\" style=\"font-size:0pt; line-height:0pt; text-align:left\">
												<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#E2231A\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
													<tr>
														<td bgcolor=\"#E2231A\" height=\"190\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
									<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
										<tr>
											<td align=\"center\">
												<table width=\"650\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"mobile-shell\">
													<tr>
														<td class=\"td\" style=\"width:650px; min-width:650px; font-size:0pt; line-height:0pt; padding:0; margin:0; font-weight:normal;\">
															<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">
																<tr>
																	<td align=\"center\">
																		<div class=\"text-r-m-center\" style=\"color:#777777; font-family: 'Poppins', Helvetica Neue, Helvetica, Arial, sans-serif; font-size:14px; line-height:24px; text-align:center\">
																			<a href=\"https://trasladosuniversales.com.mx/\" target=\"_blank\" style=\"color:#666666; text-decoration:underline\"><span style=\"color:#666666; text-decoration:underline\">https://trasladosuniversales.com.mx/</span></a>
																			<br />
																			&copy; ". date('Y') ." ". SITE_NAME ." - Todos los derechos reservados
																		</div>
																	</td>
																</tr>
															</table>
															<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">
																<tr>
																	<td height=\"40\" class=\"spacer\" style=\"font-size:0pt; line-height:0pt; text-align:center; width:100%; min-width:100%\">&nbsp;</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
									<div style=\"font-size:0pt; line-height:0pt;\" class=\"mobile-br-15\"></div>
								</td>
							</tr>
						</table>
					</body>
				</html>";

			$this->response->result = array();
			foreach($emailList as $email) { $email = trim($email);
				$this->response->result[] = array(
					'email' => $email,
					'result' => $this->model->intento->sendEmail($email, $asunto, $body)
				);
			}
			
			return $this->response->SetResponse(true);
		});
	})/* ->add( new MiddlewareToken() ) */;
?>