<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class UniversoModel {
		private $db;
		private $table = 'enc_universo';
		private $tableE = 'enc_encuesta';
		private $tableP = 'enc_pregunta';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
		}

		public function get($ID_universo) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->where("ID_universo", $ID_universo)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'no existe el registro'); }

			return $this->response;
		}

		public function getByEncuesta($ID_encuesta, $ID_pregunta=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableP.*")
				->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
				->innerJoin("$this->tableP ON $this->table.ID_pregunta = $this->tableP.ID_pregunta AND $this->tableP.status = 1")
				->where("$this->table.ID_encuesta = $ID_encuesta")
				->where("$this->table.ID_pregunta ".($ID_pregunta!=0? "=": ">")." $ID_pregunta")
				->fetchAll();
			foreach($this->response->result as &$pregunta) {
				//$pregunta->pregunta = utf8_decode($pregunta->pregunta);
				$pregunta->pregunta = $pregunta->pregunta;
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->innerJoin("$this->tableE ON $this->table.ID_encuesta = $this->tableE.ID_encuesta")
				->innerJoin("$this->tableP ON $this->table.ID_pregunta = $this->tableP.ID_pregunta AND $this->tableP.status = 1")
				->where("$this->table.ID_encuesta = $ID_encuesta")
				->where("$this->table.ID_pregunta ".($ID_pregunta!=0? "=": ">")." $ID_pregunta")
				->fetch()->total;

			return $this->response->SetResponse(true);
		}

		public function getByPregunta($ID_pregunta, $status=0) {
			$this->response = new Response();
			$this->response->result = $this->db
				->from($this->table)
				->select("$this->tableE.nombre")
				->innerJoin("$this->tableE ON $this->tableE.ID_encuesta = $this->table.ID_encuesta")
				->innerJoin("$this->tableP ON $this->table.ID_pregunta = $this->tableP.ID_pregunta AND $this->tableP.status = 1")
				->where("$this->table.ID_pregunta = $ID_pregunta")
				->where("$this->tableE.status ".($status!=0? "=": ">")." $status")
				->fetchAll();
			foreach($this->response->result as &$encuesta) {
				//$encuesta->nombre = utf8_decode($encuesta->nombre);
				$encuesta->nombre = $encuesta->nombre;
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->innerJoin("$this->tableE ON $this->tableE.ID_encuesta = $this->table.ID_encuesta")
				->innerJoin("$this->tableP ON $this->table.ID_pregunta = $this->tableP.ID_pregunta AND $this->tableP.status = 1")
				->where("$this->table.ID_pregunta = $ID_pregunta")
				->where("$this->tableE.status ".($status!=0? "=": ">")." $status")
				->fetch()->total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			$this->response = new Response(); $fields = array(); $values = array(); 
			//foreach($data as $field => $value) { $fields[] = $field; $values[] = "'".utf8_encode($value)."'"; }
			foreach($data as $field => $value) { $fields[] = $field; $values[] = "'".$value."'"; }
			try{
				$this->response->result = $this->db->getPdo()->prepare("INSERT INTO $this->table(".implode(', ', $fields).") VALUES(".implode(', ', $values).")")->execute();

				if($this->response->result!=0) { 
					$this->response->result = $this->db->getPdo()->query("SELECT MAX(ID_universo) AS ID_universo FROM $this->table")->fetch()->ID_universo; 
					$this->response->SetResponse(true, 'id del registro: '.$this->response->result); 
				} else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function del($ID_universo) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('ID_universo', $ID_universo)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, "id baja: $ID_universo");
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

		public function delByEncuesta($ID_encuesta, $ID_pregunta=0) {
			$this->response = new Response();
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('ID_encuesta', $ID_encuesta)
					->where("ID_pregunta ".($ID_pregunta!=0? "=": ">")." $ID_pregunta")
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}
	}
?>