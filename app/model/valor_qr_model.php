<?php
namespace App\Model;

use App\Lib\Response;

class ValorQrModel {
    private $db;
    private $table = 'enc_valor_qr';
    private $tblResp = 'enc_valor_resp';
    private $response;

    public function __CONSTRUCT($db) {
        $this->db = $db;
    }

    public function getAll() {
        $this->response = new Response();
        $this->response->result = $this->db
            ->from($this->table)
            ->orderBy('codigo ASC')
            ->fetchAll();

        $this->response->total = count($this->response->result);
        return $this->response->SetResponse(true);
    }

    public function getByCodigo($codigo) {
        $this->response = new Response();
        $this->response->result = $this->db
            ->from($this->table)
            ->where('codigo', $codigo)
            ->fetch();

        if($this->response->result) {
            $this->response->SetResponse(true);
        } else {
            $this->response->SetResponse(false, 'No existe el codigo solicitado');
        }

        return $this->response;
    }

    public function getByCodMD5($codigo) {
        $this->response = new Response();
        $this->response->result = $this->db
            ->from($this->table)
            ->where("MD5(codigo) = '$codigo'")
            ->fetch();

        if($this->response->result) {
            $this->response->SetResponse(true);
        } else {
            $this->response->SetResponse(false, 'No existe el codigo solicitado');
        }

        return $this->response;
    }

    public function getById($id) {
        $this->response = new Response();
        $this->response->result = $this->db
            ->from($this->table)
            ->where('id', $id)
            ->fetch();

        if($this->response->result) {
            $this->response->SetResponse(true);
        } else {
            $this->response->SetResponse(false, 'No existe el codigo solicitado');
        }

        return $this->response;
    }

    public function getRegistro($invitado_id){
        $this->response = new Response();
        $this->response->result = $this->db
            ->from($this->tblResp)
            ->where('invitado_id', $invitado_id)
            ->fetch();

        if($this->response->result) {
            $this->response->SetResponse(true);
        } else {
            $this->response->SetResponse(false, 'No existe el registro solicitado');
        }

        return $this->response;
    }

    public function replaceAll($values) {
        $this->response = new Response();

        try {
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();

            $pdo->exec("DELETE FROM {$this->table}");

            $stmt = $pdo->prepare("INSERT INTO {$this->table} (codigo, valor, fecha) VALUES (:codigo, :valor, :fecha)");
            foreach($values as $index => $valor) {
                $codigo = str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT);
                $stmt->execute([
                    ':codigo' => $codigo,
                    ':valor' => $valor,
                    ':fecha' => date('Y-m-d H:i:s')
                ]);
            }

            $pdo->commit();
            $this->response->result = $this->getAll()->result;
            $this->response->SetResponse(true, 'Valores QR actualizados');
        } catch(\PDOException $ex) {
            if($this->db->getPdo()->inTransaction()) {
                $this->db->getPdo()->rollBack();
            }

            $this->response->errors = $ex;
            $this->response->SetResponse(false, 'No fue posible guardar los valores QR');
        }

        return $this->response;
    }

    public function delById($id) {
        $this->response = new Response();

        try {
            $result = $this->db
                ->delete($this->table)
                ->where('id', $id)
                ->execute();

            if($result) {
                $this->response->SetResponse(true, 'Valor QR eliminado');
            } else {
                $this->response->SetResponse(false, 'No se pudo eliminar el valor QR');
            }
        } catch(\PDOException $ex) {
            $this->response->errors = $ex;
            $this->response->SetResponse(false, 'Error al eliminar el valor QR');
        }

        return $this->response;
    }
}
?>
