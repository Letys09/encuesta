<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

// Routes

/* $app->get('/[{name}]', function ($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
}); */

$app->get('/login', function (Request $request, Response $response, array $args) {
    $this->logger->info("Slim-Skeleton '/' login");

    if(!isset($_SESSION)) { session_start(); }
    try {
        return $this->renderer->render($response, "login.phtml", array('vista' => 'login'));
    } 
    catch (Throwable $e) { return $this->renderer->render($response, '404.phtml', []); }
    catch (Exception $e) { return $this->renderer->render($response, '404.phtml', []); }
});

$app->get('/valor/{valorCode}', function (Request $request, Response $response, array $args) {
    $codigo = $args['valorCode'];
    $valorQr = $this->model->valor_qr->getByCodMD5($codigo);
    if(!$valorQr->response || !$valorQr->result) {
        return $response->withStatus(404)->withHeader('Content-Type', 'text/plain')->write('Valor no encontrado');
    }

    return $this->renderer->render($response, 'valor.phtml', [
        'vista' => $valorQr->result->valor,
        'valorNombre' => $valorQr->result->valor,
        'valorId' => $valorQr->result->id,
    ]);
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    $this->logger->info("Slim-Skeleton '/' ".(isset($args['name'])?$args['name']:''));

    if(!isset($_SESSION)) { session_start(); }
    if(!isset($args['name']) || $args['name'] == '') { $args['name'] = 'preguntas'; }
    try {
        if(!isset($_SESSION['usuario'])) {
            $args['name'] = 'login';
        }

        return $this->renderer->render($response, "$args[name].phtml", array('vista' => ucfirst($args['name'])));
    } catch (Throwable $e) {
        var_dump($e);
        // return $this->renderer->render($response, '404.phtml', $params);
    } catch (Exception $e) {
        var_dump($e);
        // return $this->renderer->render($response, '404.phtml', $params);
    }
})/*  ->add(function(Request $request, Response $response, $next) {
    if(!isset($_SESSION)) { session_start(); }
    if(isset($_SESSION['token'])) {
        $JWT = new \App\Lib\JWT();
        if($JWT->descifrar($_SESSION['token'])) {
            $response = $next($request, $response);
        } else {
            return $response = $response->withRedirect(URL_ROOT.'/login');
        }
    } else {
        return $response = $response->withRedirect(URL_ROOT.'/login');
    }
    
    return $response;
}) */;

$app->get('/enc/{code}', function(Request $request, Response $response, array $args) {
    $this->logger->info("Slim-Skeleton '/' Encuesta $args[code]");
    require_once './core/defines.php';

    $nombreEncuesta = 'Enlace inválido';
    $url = $this->model->url->get(explode('_', base64_decode($args['code']))[0]);
    if($url->response && $url->result->status == 1) {
        unset($_SESSION['encuestas']);
        $encuesta = $this->model->encuesta->get($url->result->ID_encuesta)->result;
        $nombreEncuesta = $encuesta->nombre;
        $num_preguntas = $url->result->num_preguntas;

        $md5Code = md5($args['code']);
        if(isset($_SESSION['encuestas'][$md5Code]) && $_SESSION['encuestas'][$md5Code]->num_preguntas==$num_preguntas) {
            $url->preguntas = $_SESSION['encuestas'][$md5Code]->preguntas;
        } else {
            $universo = $this->model->universo->getByEncuesta($encuesta->ID_encuesta)->result;
            $url->preguntas = [];

            while(count($url->preguntas) < $num_preguntas && count($universo) > 0) {
                $posicion = rand(0, count($universo)-1);
                $pregunta = $this->model->pregunta->get($universo[$posicion]->ID_pregunta)->result;

                unset($universo[$posicion]);
                $universo = array_values($universo);

                $url->preguntas[] = $pregunta;
            }

            foreach($url->preguntas as $pregunta) {
                if($pregunta->tipo == 5) {
                    $pregunta->finalistas = $this->model->reporte->getFinalistas($pregunta->ID_pregunta)->result;
                }
            }

            if(!isset($_SESSION['encuestas'])) { $_SESSION['encuestas'] = []; }
            $encuestaInfo = new stdClass();
            $encuestaInfo->num_preguntas = $num_preguntas;
            $encuestaInfo->preguntas = $url->preguntas;
            $encuestaInfo->inicio = date('Y-m-d\TH:i:s');
            $_SESSION['encuestas'][$md5Code] = $encuestaInfo;

        }
    }
    elseif($url->response && $url->result->status == 2) { $nombreEncuesta = $this->model->encuesta->get($url->result->ID_encuesta)->result->nombre; $url->SetResponse(false, 'La URL no está recibiendo respuestas'); }
    elseif($url->response && $url->result->status ==0) { $nombreEncuesta = $this->model->encuesta->get($url->result->ID_encuesta)->result->nombre; $url->SetResponse(false, 'La URL no está está disponible'); }
    /* print_r($url);
    exit(); */
    return $this->renderer->render($response, "encuesta.phtml", array('vista'=>$nombreEncuesta, 'url'=>$url));
});

$app->post('/save-enc-value/{code}', function(Request $request, Response $response, array $args) {
    require_once './core/defines.php';
    $parsedBody = $request->getParsedBody();

    $_SESSION['encuestas'][$args['code']]->preguntas[$parsedBody['counter']]->temp_value = $parsedBody['value'];
    return $response->withJson(json_encode(['success' => true, 'response' => true]));
});

$app->post('/save-enc-invitado/{code}', function(Request $request, Response $response, array $args) {
    require_once './core/defines.php';
    $parsedBody = $request->getParsedBody();
    $invitado_id = isset($parsedBody['invitado_id']) ? trim($parsedBody['invitado_id']) : '';

    if($invitado_id === '' || !isset($_SESSION['encuestas'][$args['code']])) {
        return $response->withJson(['success' => false, 'response' => false]);
    }

    $_SESSION['encuestas'][$args['code']]->invitado_id = $invitado_id;
    return $response->withJson(['success' => true, 'response' => true]);
});