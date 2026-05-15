<?php
	namespace App\Model;

	use App\Lib\Response;

	class ReporteModel {
		private $db;
		private $table = 'enc_votacion_postulacion';
		private $tblFin = 'enc_finalista';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
		}

		public function getDashboardStats() {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$stats = $pdo->query("
				SELECT
					(SELECT COUNT(*) FROM enc_encuesta WHERE status > 0) AS total_encuestas,
					(SELECT COUNT(*) FROM enc_encuesta WHERE status = 1) AS encuestas_activas,
					(SELECT COUNT(*) FROM enc_pregunta WHERE status > 0) AS total_preguntas,
					(SELECT COUNT(*) FROM enc_intento) AS total_intentos,
					(SELECT COUNT(*) FROM enc_url WHERE status > 0) AS total_urls,
					(SELECT COALESCE(ROUND(AVG(TIMESTAMPDIFF(SECOND, inicio, final)) / 60, 1), 0)
				FROM enc_intento WHERE final > inicio) AS tiempo_promedio_min
			")->fetch();

			$intentosMes = $pdo->query("
				SELECT
					DATE_FORMAT(inicio, '%Y-%m') AS mes,
					DATE_FORMAT(inicio, '%b %Y') AS etiqueta,
					COUNT(*) AS total
				FROM enc_intento
				WHERE inicio >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
				GROUP BY mes, etiqueta
				ORDER BY mes ASC
			")->fetchAll();

			$topEncuestas = $pdo->query("
				SELECT e.ID_encuesta, e.nombre, COUNT(i.ID_intento) AS total_intentos
				FROM enc_encuesta e
				LEFT JOIN enc_url eu ON eu.ID_encuesta = e.ID_encuesta
				LEFT JOIN enc_intento i ON i.ID_url = eu.ID_url
				WHERE e.status = 1
				GROUP BY e.ID_encuesta, e.nombre
				ORDER BY total_intentos DESC
				LIMIT 5
			")->fetchAll();

			$distribTipos = $pdo->query("
				SELECT
					CASE tipo
						WHEN 1 THEN 'Abierta'
						WHEN 2 THEN 'Opción múltiple'
						WHEN 3 THEN 'Puntuación'
						WHEN 4 THEN 'Postulación'
						WHEN 5 THEN 'Votación'
					END AS tipo_label,
					COUNT(*) AS total
				FROM enc_pregunta
				WHERE status = 1
				GROUP BY tipo
				ORDER BY tipo
			")->fetchAll();

			$this->response->result = [
				'stats'         => $stats,
				'intentos_mes'  => $intentosMes,
				'top_encuestas' => $topEncuestas,
				'distrib_tipos' => $distribTipos,
			];

			return $this->response->SetResponse(true);
		}

		public function getResultadosByEncuesta($ID_encuesta, $fecha_desde = '', $fecha_hasta = '') {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$stmtEnc = $pdo->prepare("SELECT ID_encuesta, nombre FROM enc_encuesta WHERE ID_encuesta = ?");
			$stmtEnc->execute([(int)$ID_encuesta]);
			$encuestaInfo = $stmtEnc->fetch();

			if (!$encuestaInfo) {
				return $this->response->SetResponse(false, 'No existe la encuesta');
			}

			$whereDate = '';
			$params = [(int)$ID_encuesta];

			// Validate date format to prevent injection
			if ($fecha_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
				$whereDate .= " AND DATE(ei.inicio) >= ?";
				$params[] = $fecha_desde;
			}
			if ($fecha_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
				$whereDate .= " AND DATE(ei.inicio) <= ?";
				$params[] = $fecha_hasta;
			}

			$stmtTotal = $pdo->prepare("
				SELECT COUNT(DISTINCT ei.ID_intento) AS total
				FROM enc_url eu_url
				INNER JOIN enc_intento ei ON ei.ID_url = eu_url.ID_url
				WHERE eu_url.ID_encuesta = ? $whereDate
			");
			$stmtTotal->execute($params);
			$totalIntentos = (int)$stmtTotal->fetch()->total;

			$stmtPreguntas = $pdo->prepare("
				SELECT DISTINCT
					p.ID_pregunta,
					p.pregunta,
					p.tipo,
					p.opciones,
					p.escala,
					p.icono
				FROM enc_universo eu_univ
				INNER JOIN enc_pregunta p ON p.ID_pregunta = eu_univ.ID_pregunta
				WHERE eu_univ.ID_encuesta = ?
				ORDER BY p.ID_pregunta ASC
			");
			$stmtPreguntas->execute([(int)$ID_encuesta]);
			$preguntasBase = $stmtPreguntas->fetchAll();

			$sql = "
				SELECT
					p.ID_pregunta,
					r.respuesta,
					COUNT(*) AS total
				FROM enc_universo eu_univ
				INNER JOIN enc_pregunta p ON p.ID_pregunta = eu_univ.ID_pregunta
				INNER JOIN enc_url eu_url ON eu_url.ID_encuesta = eu_univ.ID_encuesta
				INNER JOIN enc_intento ei ON ei.ID_url = eu_url.ID_url
				INNER JOIN enc_respuesta r ON r.ID_intento = ei.ID_intento AND r.ID_pregunta = p.ID_pregunta
				WHERE eu_univ.ID_encuesta = ? $whereDate
				GROUP BY p.ID_pregunta, r.respuesta
				ORDER BY p.ID_pregunta ASC, total DESC
			";
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$rawRows = $stmt->fetchAll();

			$stmtRating = $pdo->prepare("
				SELECT
					p.ID_pregunta,
					ROUND(AVG(CAST(r.respuesta AS DECIMAL(10,2))), 2) AS promedio,
					MIN(CAST(r.respuesta AS DECIMAL(10,2))) AS minimo,
					MAX(CAST(r.respuesta AS DECIMAL(10,2))) AS maximo
				FROM enc_universo eu_univ
				INNER JOIN enc_pregunta p ON p.ID_pregunta = eu_univ.ID_pregunta AND p.tipo = 3
				INNER JOIN enc_url eu_url ON eu_url.ID_encuesta = eu_univ.ID_encuesta
				INNER JOIN enc_intento ei ON ei.ID_url = eu_url.ID_url
				INNER JOIN enc_respuesta r ON r.ID_intento = ei.ID_intento AND r.ID_pregunta = p.ID_pregunta
				WHERE eu_univ.ID_encuesta = ? $whereDate
				GROUP BY p.ID_pregunta
			");
			$stmtRating->execute($params);
			$ratingRows = $stmtRating->fetchAll();

			$ratingSummary = [];
			foreach ($ratingRows as $ratingRow) {
				$ratingSummary[$ratingRow->ID_pregunta] = [
					'promedio' => $ratingRow->promedio !== null ? (float)$ratingRow->promedio : null,
					'minimo'   => $ratingRow->minimo !== null ? (float)$ratingRow->minimo : null,
					'maximo'   => $ratingRow->maximo !== null ? (float)$ratingRow->maximo : null,
				];
			}

			$preguntas = [];
			foreach ($preguntasBase as $preguntaBase) {
				$id = $preguntaBase->ID_pregunta;
				$preguntas[$id] = [
					'ID_pregunta'        => $id,
					'pregunta'           => $preguntaBase->pregunta,
					'tipo'               => (int)$preguntaBase->tipo,
					'opciones'           => $preguntaBase->opciones,
					'escala'             => $preguntaBase->escala,
					'icono'              => $preguntaBase->icono,
					'respuestas'         => [],
					'total_respuestas'   => 0,
					'sin_respuesta'      => $totalIntentos,
					'porcentaje_cobertura' => 0,
					'respuestas_unicas'  => 0,
					'respuesta_top'      => null,
					'metricas'           => [
						'promedio' => null,
						'minimo'   => null,
						'maximo'   => null,
					],
				];
			}

			foreach ($rawRows as $row) {
				$id = $row->ID_pregunta;
				if (!isset($preguntas[$id])) {
					continue;
				}

				$preguntas[$id]['respuestas'][] = [
					'valor' => $row->respuesta,
					'total' => (int)$row->total,
				];
				$preguntas[$id]['total_respuestas'] += (int)$row->total;
			}

			foreach ($preguntas as &$pregunta) {
				if ($pregunta['tipo'] === 2) {
					$opcionesConfiguradas = array_filter(array_map('trim', explode(',', (string)$pregunta['opciones'])));
					$mapaRespuestas = [];
					foreach ($pregunta['respuestas'] as $respuesta) {
						$mapaRespuestas[(string)$respuesta['valor']] = $respuesta['total'];
					}

					$pregunta['respuestas'] = [];
					foreach ($opcionesConfiguradas as $opcion) {
						$pregunta['respuestas'][] = [
							'valor' => $opcion,
							'total' => isset($mapaRespuestas[$opcion]) ? (int)$mapaRespuestas[$opcion] : 0,
						];
					}
				}

				if ($pregunta['tipo'] === 3 && !empty($pregunta['escala'])) {
					$partesEscala = explode('-', $pregunta['escala']);
					if (count($partesEscala) === 2) {
						$inicioEscala = (int)$partesEscala[0];
						$finEscala = (int)$partesEscala[1];
						$mapaRespuestas = [];
						foreach ($pregunta['respuestas'] as $respuesta) {
							$mapaRespuestas[(string)$respuesta['valor']] = $respuesta['total'];
						}

						$pregunta['respuestas'] = [];
						for ($valorEscala = $inicioEscala; $valorEscala <= $finEscala; $valorEscala++) {
							$pregunta['respuestas'][] = [
								'valor' => (string)$valorEscala,
								'total' => isset($mapaRespuestas[(string)$valorEscala]) ? (int)$mapaRespuestas[(string)$valorEscala] : 0,
							];
						}
					}
				}

				$pregunta['sin_respuesta'] = max(0, $totalIntentos - $pregunta['total_respuestas']);
				$pregunta['porcentaje_cobertura'] = $totalIntentos > 0
					? round(($pregunta['total_respuestas'] / $totalIntentos) * 100, 1)
					: 0;
				$pregunta['respuestas_unicas'] = count(array_filter($pregunta['respuestas'], function($respuesta) {
					return (int)$respuesta['total'] > 0;
				}));

				$respuestaTop = null;
				foreach ($pregunta['respuestas'] as $respuesta) {
					if ($respuestaTop === null || (int)$respuesta['total'] > (int)$respuestaTop['total']) {
						$respuestaTop = $respuesta;
					}
				}
				if ($respuestaTop !== null && (int)$respuestaTop['total'] > 0) {
					$pregunta['respuesta_top'] = $respuestaTop;
				}

				if (isset($ratingSummary[$pregunta['ID_pregunta']])) {
					$pregunta['metricas'] = $ratingSummary[$pregunta['ID_pregunta']];
				}
			}
			unset($pregunta);

			$totalPreguntas = count($preguntas);
			$preguntasConRespuesta = 0;
			$acumuladoCobertura = 0;
			foreach ($preguntas as $pregunta) {
				if ($pregunta['total_respuestas'] > 0) {
					$preguntasConRespuesta++;
				}
				$acumuladoCobertura += $pregunta['porcentaje_cobertura'];
			}

			$preguntasPostulacion = array_values(array_filter($preguntas, function($p) {
				return (int)$p['tipo'] === 4;
			}));

			$this->response->result = [
				'encuesta'             => $encuestaInfo,
				'total_intentos'       => $totalIntentos,
				'resumen'              => [
					'total_preguntas'         => $totalPreguntas,
					'preguntas_con_respuesta' => $preguntasConRespuesta,
					'cobertura_promedio'      => $totalPreguntas > 0 ? round($acumuladoCobertura / $totalPreguntas, 1) : 0,
					'fecha_desde'             => $fecha_desde,
					'fecha_hasta'             => $fecha_hasta,
				],
				'preguntas'            => array_values($preguntas),
				'tiene_postulacion'    => count($preguntasPostulacion) > 0,
				'preguntas_postulacion' => array_map(function($p) {
					return ['ID_pregunta' => $p['ID_pregunta'], 'pregunta' => $p['pregunta']];
				}, $preguntasPostulacion),
			];

			return $this->response->SetResponse(true);
		}

		public function getRespuestasExport($ID_encuesta, $fecha_desde = '', $fecha_hasta = '') {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$stmtEnc = $pdo->prepare("SELECT ID_encuesta, nombre FROM enc_encuesta WHERE ID_encuesta = ?");
			$stmtEnc->execute([(int)$ID_encuesta]);
			$encuestaInfo = $stmtEnc->fetch();

			if (!$encuestaInfo) {
				return $this->response->SetResponse(false, 'No existe la encuesta');
			}

			$whereDate = '';
			$params = [(int)$ID_encuesta];

			if ($fecha_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
				$whereDate .= " AND DATE(ei.inicio) >= ?";
				$params[] = $fecha_desde;
			}
			if ($fecha_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
				$whereDate .= " AND DATE(ei.inicio) <= ?";
				$params[] = $fecha_hasta;
			}

			// Preguntas de la encuesta (ordered)
			$stmtPreguntas = $pdo->prepare("
				SELECT p.ID_pregunta, p.pregunta, p.tipo
				FROM enc_universo eu
				INNER JOIN enc_pregunta p ON p.ID_pregunta = eu.ID_pregunta
				WHERE eu.ID_encuesta = ?
				ORDER BY p.ID_pregunta ASC
			");
			$stmtPreguntas->execute([(int)$ID_encuesta]);
			$preguntas = $stmtPreguntas->fetchAll();

			// All intentos with their responses
			$stmtIntentos = $pdo->prepare("
				SELECT ei.ID_intento, ei.invitado_id, ei.inicio, ei.final
				FROM enc_url eu_url
				INNER JOIN enc_intento ei ON ei.ID_url = eu_url.ID_url
				WHERE eu_url.ID_encuesta = ? $whereDate
				ORDER BY ei.inicio ASC
			");
			$stmtIntentos->execute($params);
			$intentos = $stmtIntentos->fetchAll();

			// All responses for those intentos
			$intentoIds = array_map(function($i) { return (int)$i->ID_intento; }, $intentos);

			$respuestasPorIntento = [];
			if (count($intentoIds) > 0) {
				$placeholders = implode(',', array_fill(0, count($intentoIds), '?'));
				$stmtResp = $pdo->prepare("
					SELECT ID_intento, ID_pregunta, respuesta
					FROM enc_respuesta
					WHERE ID_intento IN ($placeholders)
				");
				$stmtResp->execute($intentoIds);
				foreach ($stmtResp->fetchAll() as $r) {
					$respuestasPorIntento[$r->ID_intento][$r->ID_pregunta] = $r->respuesta;
				}
			}

			$rows = [];
			foreach ($intentos as $idx => $intento) {
				$row = [
					'num'    => $intento->invitado_id,
					'inicio' => $intento->inicio,
				];
				foreach ($preguntas as $p) {
					$row['p_'.$p->ID_pregunta] = isset($respuestasPorIntento[$intento->ID_intento][$p->ID_pregunta])
						? $respuestasPorIntento[$intento->ID_intento][$p->ID_pregunta]
						: '';
				}
				$rows[] = $row;
			}

			$this->response->result = [
				'encuesta'  => $encuestaInfo,
				'preguntas' => $preguntas,
				'filas'     => $rows,
				'desde'     => $fecha_desde,
				'hasta'     => $fecha_hasta,
			];

			return $this->response->SetResponse(true);
		}

		public function getValoresVotos($fecha_desde = '', $fecha_hasta = '') {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$joinDate = '';
			$params = [];

			if ($fecha_desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
				$joinDate .= " AND DATE(r.fecha) >= ?";
				$params[] = $fecha_desde;
			}
			if ($fecha_hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
				$joinDate .= " AND DATE(r.fecha) <= ?";
				$params[] = $fecha_hasta;
			}

			$stmt = $pdo->prepare(" 
				SELECT
					v.id,
					v.codigo,
					v.valor,
					COUNT(r.id) AS votos,
					COALESCE(GROUP_CONCAT(DISTINCT r.invitado_id ORDER BY r.invitado_id SEPARATOR ', '), '') AS invitados
				FROM enc_valor_qr v
				LEFT JOIN enc_valor_resp r ON r.enc_valor_qr_id = v.id $joinDate
				GROUP BY v.id, v.codigo, v.valor
				ORDER BY v.codigo ASC
			");

			$stmt->execute($params);
			$rows = $stmt->fetchAll();

			$totalVotos = 0;
			foreach ($rows as $row) {
				$totalVotos += (int)$row->votos;
			}

			$this->response->result = [
				'valores' => $rows,
				'total_votos' => $totalVotos,
				'total_valores' => count($rows),
				'desde' => $fecha_desde,
				'hasta' => $fecha_hasta,
			];

			return $this->response->SetResponse(true);
		}

		public function getPostulados($ID_encuesta, $ID_pregunta) {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$stmt = $pdo->prepare("
				SELECT DISTINCT
					ei.invitado_id,
					ei.nombre,
					CASE WHEN ef.id IS NOT NULL THEN 1 ELSE 0 END AS es_finalista
				FROM enc_url eu
				INNER JOIN enc_intento ei ON ei.ID_url = eu.ID_url
				INNER JOIN enc_respuesta r ON r.ID_intento = ei.ID_intento AND r.ID_pregunta = ?
				LEFT JOIN enc_finalista ef
					ON ef.ID_encuesta = ? AND ef.ID_pregunta = ? AND ef.invitado_id = ei.invitado_id
				WHERE eu.ID_encuesta = ?
				ORDER BY ei.invitado_id ASC
			");
			$stmt->execute([(int)$ID_pregunta, (int)$ID_encuesta, (int)$ID_pregunta, (int)$ID_encuesta]);
			$rows = $stmt->fetchAll();

			$this->response->result = $rows;
			$this->response->total  = count($rows);
			return $this->response->SetResponse(true);
		}

		public function getVotingQuestionStatus($ID_encuesta, $ID_pregunta_postulacion) {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$stmt = $pdo->prepare(" 
				SELECT vp.ID_pregunta_votacion
				FROM enc_votacion_postulacion vp
				INNER JOIN enc_pregunta pv ON pv.ID_pregunta = vp.ID_pregunta_votacion
				WHERE vp.ID_encuesta = ?
				  AND vp.ID_pregunta_postulacion = ?
				  AND pv.tipo = 5
				  AND pv.status > 0
				LIMIT 1
			");
			$stmt->execute([(int)$ID_encuesta, (int)$ID_pregunta_postulacion]);
			$row = $stmt->fetch();

			$this->response->result = [
				'exists' => $row ? true : false,
				'ID_pregunta_votacion' => $row ? (int)$row->ID_pregunta_votacion : 0,
			];

			return $this->response->SetResponse(true);
		}

		public function saveFinalists($ID_encuesta, $ID_pregunta, $invitados) {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$invitados = array_values(array_unique(array_map(function($value) {
				return trim((string)$value);
			}, (array)$invitados)));
			$invitados = array_values(array_filter($invitados, function($value) {
				return $value !== '';
			}));

			if (count($invitados) > 10) {
				return $this->response->SetResponse(false, 'Puedes seleccionar máximo 10 finalistas');
			}

			try {
				$pdo->beginTransaction();

				$stmtDel = $pdo->prepare("DELETE FROM enc_finalista WHERE ID_encuesta = ? AND ID_pregunta = ?");
				$stmtDel->execute([(int)$ID_encuesta, (int)$ID_pregunta]);

				if (count($invitados) > 0) {
					$stmtIns = $pdo->prepare("INSERT INTO enc_finalista (ID_encuesta, ID_pregunta, invitado_id, fecha) VALUES (?, ?, ?, NOW())");
					foreach ($invitados as $invitado_id) {
						$stmtIns->execute([(int)$ID_encuesta, (int)$ID_pregunta, trim((string)$invitado_id)]);
					}
				}

				$stmtVotacion = $pdo->prepare(" 
					SELECT vp.ID_pregunta_votacion
					FROM enc_votacion_postulacion vp
					INNER JOIN enc_pregunta pv ON pv.ID_pregunta = vp.ID_pregunta_votacion
					WHERE vp.ID_encuesta = ?
					  AND vp.ID_pregunta_postulacion = ?
					  AND pv.tipo = 5
					  AND pv.status > 0
					LIMIT 1
				");
				$stmtVotacion->execute([(int)$ID_encuesta, (int)$ID_pregunta]);
				$votacion = $stmtVotacion->fetch();

				$votacionExistente = false;
				$ID_pregunta_votacion = 0;
				if ($votacion) {
					$votacionExistente = true;
					$ID_pregunta_votacion = (int)$votacion->ID_pregunta_votacion;

					$stmtDelVot = $pdo->prepare("DELETE FROM enc_finalista WHERE ID_encuesta = ? AND ID_pregunta = ?");
					$stmtDelVot->execute([(int)$ID_encuesta, $ID_pregunta_votacion]);

					if (count($invitados) > 0) {
						$stmtInsVot = $pdo->prepare("INSERT INTO enc_finalista (ID_encuesta, ID_pregunta, invitado_id, fecha) VALUES (?, ?, ?, NOW())");
						foreach ($invitados as $invitado_id) {
							$stmtInsVot->execute([(int)$ID_encuesta, $ID_pregunta_votacion, $invitado_id]);
						}
					}
				}

				$pdo->commit();
				$this->response->result = [
					'total_finalistas' => count($invitados),
					'votacion_existente' => $votacionExistente,
					'ID_pregunta_votacion' => $ID_pregunta_votacion,
				];
				return $this->response->SetResponse(true, $votacionExistente ? 'Finalistas actualizados y sincronizados con la pregunta de votación existente' : 'Finalistas guardados correctamente');
			} catch (\PDOException $ex) {
				$pdo->rollBack();
				$this->response->errors = $ex->getMessage();
				return $this->response->SetResponse(false, 'Error al guardar los finalistas');
			}
		}

		public function createVotingQuestion($ID_encuesta, $ID_pregunta_postulacion, $pregunta_texto) {
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$ID_encuesta = (int)$ID_encuesta;
			$ID_pregunta_postulacion = (int)$ID_pregunta_postulacion;
			$pregunta_texto = trim((string)$pregunta_texto);

			if ($pregunta_texto === '') {
				return $this->response->SetResponse(false, 'Debe ingresar el texto de la pregunta');
			}

			try {
				$pdo->beginTransaction();

				$stmtVal = $pdo->prepare(" 
					SELECT COUNT(*) AS total
					FROM enc_universo u
					INNER JOIN enc_pregunta p ON p.ID_pregunta = u.ID_pregunta
					WHERE u.ID_encuesta = ? AND u.ID_pregunta = ? AND p.tipo = 4 AND p.status = 1
				");
				$stmtVal->execute([$ID_encuesta, $ID_pregunta_postulacion]);
				$val = (int)$stmtVal->fetch()->total;
				if ($val === 0) {
					$pdo->rollBack();
					return $this->response->SetResponse(false, 'La pregunta de postulación no es válida para la encuesta seleccionada');
				}

				$stmtExisteVotacion = $pdo->prepare(" 
					SELECT vp.ID_pregunta_votacion
					FROM enc_votacion_postulacion vp
					INNER JOIN enc_pregunta pv ON pv.ID_pregunta = vp.ID_pregunta_votacion
					WHERE vp.ID_encuesta = ?
					  AND vp.ID_pregunta_postulacion = ?
					  AND pv.tipo = 5
					  AND pv.status > 0
					LIMIT 1
				");
				$stmtExisteVotacion->execute([$ID_encuesta, $ID_pregunta_postulacion]);
				$votacionExistente = $stmtExisteVotacion->fetch();
				if ($votacionExistente) {
					$pdo->rollBack();
					$this->response->result = [
						'ID_pregunta' => (int)$votacionExistente->ID_pregunta_votacion,
						'already_exists' => true,
					];
					return $this->response->SetResponse(false, 'Ya existe una pregunta de votación para esta postulación');
				}

				$stmtFinalistas = $pdo->prepare(" 
					SELECT invitado_id
					FROM enc_finalista
					WHERE ID_encuesta = ? AND ID_pregunta = ?
					ORDER BY id ASC
				");
				$stmtFinalistas->execute([$ID_encuesta, $ID_pregunta_postulacion]);
				$finalistas = $stmtFinalistas->fetchAll();

				if (count($finalistas) === 0) {
					$pdo->rollBack();
					return $this->response->SetResponse(false, 'No hay finalistas guardados para crear la votación');
				}

				$stmtInsPregunta = $pdo->prepare(" 
					INSERT INTO enc_pregunta (pregunta, tipo, opciones, escala, icono, fecha, status)
					VALUES (?, 5, '', '', NULL, NOW(), 1)
				");
				$stmtInsPregunta->execute([$pregunta_texto]);
				$ID_pregunta_votacion = (int)$pdo->lastInsertId();

				$stmtInsUniverso = $pdo->prepare("INSERT INTO enc_universo (ID_encuesta, ID_pregunta) VALUES (?, ?)");
				$stmtInsUniverso->execute([$ID_encuesta, $ID_pregunta_votacion]);

				$stmtInsFinal = $pdo->prepare(" 
					INSERT INTO enc_finalista (ID_encuesta, ID_pregunta, invitado_id, fecha)
					VALUES (?, ?, ?, NOW())
				");
				foreach ($finalistas as $finalista) {
					$stmtInsFinal->execute([$ID_encuesta, $ID_pregunta_votacion, $finalista->invitado_id]);
				}

				$stmtInsVinculo = $pdo->prepare(" 
					INSERT INTO enc_votacion_postulacion (ID_encuesta, ID_pregunta_postulacion, ID_pregunta_votacion, fecha)
					VALUES (?, ?, ?, NOW())
				");
				$stmtInsVinculo->execute([$ID_encuesta, $ID_pregunta_postulacion, $ID_pregunta_votacion]);

				$pdo->commit();

				$this->response->result = [
					'ID_pregunta' => $ID_pregunta_votacion,
					'tipo' => 5,
					'total_finalistas' => count($finalistas),
				];
				return $this->response->SetResponse(true, 'Pregunta de votación creada correctamente');
			} catch (\PDOException $ex) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}
				$this->response->errors = $ex->getMessage();
				return $this->response->SetResponse(false, 'Error al crear la pregunta de votación');
			}
		}

		public function getFinalistas($id){
			$this->response = new Response();
			$pdo = $this->db->getPdo();

			$stmt = $pdo->prepare(" 
				SELECT 
					f.invitado_id,
					COALESCE(NULLIF(r.respuesta, ''), NULLIF(i.nombre, ''), f.invitado_id) AS texto
				FROM enc_votacion_postulacion vp
				INNER JOIN enc_finalista f 
					ON f.ID_pregunta = vp.ID_pregunta_postulacion 
					AND f.ID_encuesta = vp.ID_encuesta
				LEFT JOIN (
					SELECT 
						eu.ID_encuesta,
						ei.invitado_id,
						MAX(ei.ID_intento) AS ID_intento
					FROM enc_intento ei
					INNER JOIN enc_url eu ON eu.ID_url = ei.ID_url
					GROUP BY eu.ID_encuesta, ei.invitado_id
				) li 
					ON li.ID_encuesta = vp.ID_encuesta 
					AND li.invitado_id = f.invitado_id
				LEFT JOIN enc_intento i ON i.ID_intento = li.ID_intento
				LEFT JOIN enc_respuesta r 
					ON r.ID_intento = i.ID_intento 
					AND r.ID_pregunta = vp.ID_pregunta_postulacion
				WHERE vp.ID_pregunta_votacion = ?
				ORDER BY f.id ASC
			");

			$stmt->execute([(int)$id]);
			$this->response->result = $stmt->fetchAll();

			return $this->response->SetResponse(true);
		}
	}
?>
