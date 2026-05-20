<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class UrlModel {
		private $db;
		private $table = 'enc_url';
		private $tableE = 'enc_encuesta';
		private $tableI = 'enc_intento';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
		}

		public function get($ID_url) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('ID_url', $ID_url)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true,' '); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getByEncuesta($ID_encuesta, $status=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->leftJoin("$this->tableE ON $this->tableE.ID_encuesta = $this->table.ID_encuesta")
				->where("$this->table.ID_encuesta = $ID_encuesta")
				->where("$this->table.status ".($status!=0? "=": ">")." $status")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->leftJoin("$this->tableE ON $this->tableE.ID_encuesta = $this->table.ID_encuesta")
				->where("$this->table.ID_encuesta = $ID_encuesta")
				->where("$this->table.status ".($status!=0? "=": ">")." $status")
				->fetch()->total;

			return $this->response->SetResponse(true);
		}

		public function getAll($pagina=0, $limite=0, $busqueda='0') {
			$this->response = new Response();
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->select("nombre, TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)) AS url, COALESCE(num_intentos, 0) AS num_intentos")
					->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
					->leftJoin("(SELECT ID_url, COUNT(*) AS num_intentos FROM $this->tableI GROUP BY ID_url) $this->tableI ON $this->table.ID_url = $this->tableI.ID_url")
					->where("CONCAT(CASE WHEN CHAR_LENGTH($this->table.ID_url)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', $this->table.ID_url), 3)) ELSE CONCAT('P-', $this->table.ID_url) END, ' ', DATE_FORMAT($this->table.fecha, '%d/%m/%Y'), ' ', $this->tableE.nombre, ' ', TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)), ' ', num_preguntas, ' ', COALESCE(num_intentos, 0), ' ', CASE WHEN $this->table.status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
					->where("$this->table.status > 0")
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select("nombre, TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)) AS url, COALESCE(num_intentos, 0) AS num_intentos")
					->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
					->leftJoin("(SELECT ID_url, COUNT(*) AS num_intentos FROM $this->tableI GROUP BY ID_url) $this->tableI ON $this->table.ID_url = $this->tableI.ID_url")
					->where("CONCAT(CASE WHEN CHAR_LENGTH($this->table.ID_url)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', $this->table.ID_url), 3)) ELSE CONCAT('P-', $this->table.ID_url) END, ' ', DATE_FORMAT($this->table.fecha, '%d/%m/%Y'), ' ', $this->tableE.nombre, ' ', TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)), ' ', num_preguntas, ' ', COALESCE(num_intentos, 0), ' ', CASE WHEN $this->table.status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
					->where("$this->table.status > 0")
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
				->leftJoin("(SELECT ID_url, COUNT(*) AS num_intentos FROM $this->tableI GROUP BY ID_url) $this->tableI ON $this->table.ID_url = $this->tableI.ID_url")
				->where("CONCAT(CASE WHEN CHAR_LENGTH($this->table.ID_url)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', $this->table.ID_url), 3)) ELSE CONCAT('P-', $this->table.ID_url) END, ' ', DATE_FORMAT($this->table.fecha, '%d/%m/%Y'), ' ', $this->tableE.nombre, ' ', TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)), ' ', num_preguntas, ' ', COALESCE(num_intentos, 0), ' ', CASE WHEN $this->table.status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
				->where("$this->table.status > 0")
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getAllDataTables($inicial, $limite, $busqueda, $orden='fecha desc') {
			$this->response = new Response();
			$busqueda = $busqueda!='0'? $busqueda: '_';
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableE.nombre, COALESCE(num_intentos, 0) AS num_intentos, TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)) AS url, $this->table.urltxt ")
				->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
				->leftJoin("(SELECT ID_url, COUNT(*) AS num_intentos FROM $this->tableI GROUP BY ID_url) $this->tableI ON $this->table.ID_url = $this->tableI.ID_url")
				->where("CONCAT(CASE WHEN CHAR_LENGTH($this->table.ID_url)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', $this->table.ID_url), 3)) ELSE CONCAT('P-', $this->table.ID_url) END, ' ', DATE_FORMAT($this->table.fecha, '%d/%m/%Y'), ' ', $this->tableE.nombre, ' ', TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)), ' ', num_preguntas, ' ', COALESCE(num_intentos, 0), ' ', CASE WHEN $this->table.status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
				->where("$this->table.status > 0")
				->orderBy($orden)
				->limit("$inicial, $limite")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where("$this->table.status > 0")
				->fetch()
				->total;

			$this->response->filtered = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
				->leftJoin("(SELECT ID_url, COUNT(*) AS num_intentos FROM $this->tableI GROUP BY ID_url) $this->tableI ON $this->table.ID_url = $this->tableI.ID_url")
				->where("CONCAT(CASE WHEN CHAR_LENGTH($this->table.ID_url)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', $this->table.ID_url), 3)) ELSE CONCAT('P-', $this->table.ID_url) END, ' ', DATE_FORMAT($this->table.fecha, '%d/%m/%Y'), ' ', $this->tableE.nombre, ' ', TO_BASE64(CONCAT($this->table.ID_url, '_', $this->table.fecha)), ' ', num_preguntas, ' ', COALESCE(num_intentos, 0), ' ', CASE WHEN $this->table.status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
				->where("$this->table.status > 0")
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getDisp(){
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select(null)
				->select("ID_url, fecha")
				->where("ID_encuesta = 0")
				->where("status = 2")
				->fetch();
			if($this->response->result) return $this->response->SetResponse(true,' '); 
			else return $this->response->SetResponse(false, 'no hay registro disponible'); 
		}

		public function add($data) {
			$this->response = new Response(); $fields = array(); $values = array(); 
			foreach($data as $field => $value) { $fields[] = $field; $values[] = "'".utf8_encode($value)."'"; }
			try{
				$this->response->result = $this->db->getPdo()->prepare("INSERT INTO $this->table(".implode(', ', $fields).") VALUES(".implode(', ', $values).")")->execute();

				if($this->response->result!=0) { 
					$this->response->result = $this->db->getPdo()->query("SELECT MAX(ID_url) AS ID_url FROM $this->table")->fetch()->ID_url; 
					$this->response->SetResponse(true, 'id del registro: '.$this->response->result); 
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		/****
		 * 
		 * Se guarda el URLtxt en el nuevo campo para que lo utilicen en otro modulo
		 * 
		 * 
		 * 
		 * 
		 */

		public function updateURL($ID_url, $urltxt) {
			$this->response = new Response();
			$this->response->result = true;

			try{

				$data['urltxt'] = $urltxt;
					$this->response->result = $this->db
						->update($this->table, $data)
						->where('ID_url', $ID_url)
						->execute();
				

				if($this->response->result)	{ $this->response->SetResponse(true, 'id actualizado: '.$ID_url); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}




		public function edit($data, $ID_url) {
			$this->response = new Response();
			$this->response->result = true;
			try{
				$orgInfo = (array) $this->get($ID_url)->result; $areTheSame = true;
				foreach($orgInfo as $field => $value) { if(isset($data[$field]) && $data[$field] != $value) { $areTheSame = false; break; } }
				if(!$areTheSame) {
					foreach($data as $field => $value) { $data[$field] = $value; }
					$this->response->result = $this->db
						->update($this->table, $data)
						->where('ID_url', $ID_url)
						->execute();
				}

				if($this->response->result)	{ $this->response->SetResponse(true, 'id actualizado: '.$ID_url); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function del($ID_url) {
			$this->response = new Response();
			$this->response->result = true;
			try{
				$data['status'] = 0;
				$orgStatus = $this->get($ID_url)->result->status;
				if($orgStatus != $data['status']) {
					$this->response->result = $this->db
						->update($this->table, $data)
						->where('ID_url', $ID_url)
						->execute();
				}

				if($this->response->result)	{ $this->response->SetResponse(true, 'id baja: '.$ID_url); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}
	}
?>