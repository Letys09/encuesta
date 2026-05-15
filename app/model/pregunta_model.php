<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class PreguntaModel {
		private $db;
		private $table = 'enc_pregunta';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
		}

		public function getByPregunta($pregunta) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where("pregunta = '$pregunta'")
				->where("status > 0")
				->fetchAll();
			if(count($this->response->result) > 0) { 
				foreach($this->response->result as &$pregunta) {
					//$pregunta->pregunta = utf8_decode($pregunta->pregunta);
					$pregunta->pregunta = $pregunta->pregunta;
				}

				$this->response->SetResponse(true); 
			} else { $this->response->SetResponse(false, 'NO se encontró ningun registro'); }

			return $this->response;
		}

		public function get($ID_pregunta) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where('ID_pregunta', $ID_pregunta)
				->fetch();

			//if($this->response->result) { $this->response->result->pregunta = utf8_decode($this->response->result->pregunta); $this->response->SetResponse(true,' '); }
			if($this->response->result) { $this->response->result->pregunta = $this->response->result->pregunta; $this->response->SetResponse(true,' '); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getAll($pagina=0, $limite=0, $busqueda='0', $status=0) {
			$this->response = new Response();
			$busqueda = $busqueda!='0'? $busqueda: '_';
			if($limite == 0) {
				$this->response->result = $this->db
					->from($this->table)
					->where("CONCAT(CASE WHEN CHAR_LENGTH(ID_pregunta)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', ID_pregunta), 3)) ELSE CONCAT('P-', ID_pregunta) END, ' ', FORMAT(fecha, 'dd/MM/yyyy'), ' ', pregunta, ' ', CASE WHEN status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
					->where("status ".($status!=0? "=": ">")." $status")
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->where("CONCAT(CASE WHEN CHAR_LENGTH(ID_pregunta)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', ID_pregunta), 3)) ELSE CONCAT('P-', ID_pregunta) END, ' ', FORMAT(fecha, 'dd/MM/yyyy'), ' ', pregunta, ' ', CASE WHEN status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
					->where("status ".($status!=0? "=": ">")." $status")
					->limit("$inicial, $limite")
					->fetchAll();
			}
			foreach($this->response->result as &$pregunta) {
				//$pregunta->pregunta = utf8_decode($pregunta->pregunta);
				$pregunta->pregunta = $pregunta->pregunta;
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("CONCAT(CASE WHEN CHAR_LENGTH(ID_pregunta)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', ID_pregunta), 3)) ELSE CONCAT('P-', ID_pregunta) END, ' ', FORMAT(fecha, 'dd/MM/yyyy'), ' ', pregunta, ' ', CASE WHEN status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
				->where("status ".($status!=0? "=": ">")." $status")
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getAllDataTables($inicial, $limite, $busqueda, $orden='pregunta asc', $status=0) {
			$this->response = new Response();
			$busqueda = $busqueda!='0'? $busqueda: '_';
			$this->response->result = $this->db
				->from($this->table)
				->where("CONCAT(CASE WHEN CHAR_LENGTH(ID_pregunta)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', ID_pregunta), 3)) ELSE CONCAT('P-', ID_pregunta) END, ' ', FORMAT(fecha, 'dd/MM/yyyy'), ' ', pregunta, ' ', CASE WHEN status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
				->where("status ".($status!=0? "=": ">")." $status")
				->orderBy($orden)
				->limit("$inicial, $limite")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where("status ".($status!=0? "=": ">")." $status")
				->fetch()
				->total;

			$this->response->filtered = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where("CONCAT(CASE WHEN CHAR_LENGTH(ID_pregunta)<3 THEN CONCAT('P-', RIGHT(CONCAT('00', ID_pregunta), 3)) ELSE CONCAT('P-', ID_pregunta) END, ' ', FORMAT(fecha, 'dd/MM/yyyy'), ' ', pregunta, ' ', CASE WHEN status=1 THEN 'activo' ELSE 'inactivo' END) LIKE '%$busqueda%'")
				->where("status ".($status!=0? "=": ">")." $status")
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			$this->response = new Response(); $fields = array(); $values = array(); 
			//foreach($data as $field => $value) { $fields[] = $field; $values[] = "'".utf8_encode($value)."'"; }

			foreach($data as $field => $value) {
				$fields[] = $field;
				$values[] = $value === null? 'NULL': "'".$value."'";
			}
			try{
				$this->response->result = $this->db->getPdo()->prepare("INSERT INTO $this->table(".implode(', ', $fields).") VALUES(".implode(', ', $values).")")->execute();

				if($this->response->result!=0) { 
					$this->response->result = $this->db->getPdo()->query("SELECT MAX(ID_pregunta) AS ID_pregunta FROM $this->table")->fetch()->ID_pregunta; 
					$this->response->SetResponse(true, 'id del registro: '.$this->response->result); 
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		/*** edit ***/
		public function edit($data, $ID_pregunta) {
			$this->response = new Response();
			$this->response->result = true;
			try{
				$orgInfo = $this->get($ID_pregunta)->result; $areTheSame = true;
				foreach($orgInfo as $field => $value) { if(isset($data[$field]) && $data[$field] != $value) { $areTheSame = false; break; } }
				if(!$areTheSame) {
					//foreach($data as $field => $value) { $data[$field] = utf8_encode($value); }
					foreach($data as $field => $value) { $data[$field] = $value; }
					$this->response->result = $this->db
						->update($this->table, $data)
						->where('ID_pregunta', $ID_pregunta)
						->execute();
				}

				if($this->response->result)	{ $this->response->SetResponse(true, 'id actualizado: '.$ID_pregunta); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function del($ID_pregunta) {
			$this->response = new Response();
			$this->response->result = true;
			try{
				$data['status'] = 0;
				$orgStatus = $this->get($ID_pregunta)->result->status;
				if($orgStatus != $data['status']) {
					$this->response->result = $this->db
						->update($this->table, $data)
						->where('ID_pregunta', $ID_pregunta)
						->execute();
				}

				if($this->response->result)	{ $this->response->SetResponse(true, 'id baja: '.$ID_pregunta); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

	}
?>