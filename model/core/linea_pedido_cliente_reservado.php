<?php
/*
 * Copyright (C) 2018 Javier Trujillo González <javimeteo@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See th * e
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

 namespace FacturaScripts\model;

class linea_pedido_cliente_reservado extends \fs_model
{

    public $idlinea;
    public $stockreservado;

    public function __construct($data = FALSE)
    {
        parent::__construct('lineaspedidosclireservado');

        if ($data) {
            $this->idlinea = $this->intval($data['idlinea']);
            $this->stockreservado = $this->intval($data['stockreservado']);
        } else {
            $this->idlinea = NULL;
            $this->stockreservado = 0;
        }
    }

    public function get($idlinea) {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idlinea = " . $idlinea . ";");

        if($data) {
            return new \linea_pedido_cliente_reservado($data[0]);
        }
        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idlinea)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idlinea = " . $this->idlinea . ";");
    }

    // Devuelve el stock asociado con la línea
    private function get_stock() {
        $linea = new \linea_pedido_cliente();
        $linea = $linea->get($this->idlinea);
        $pedido = new \pedido_cliente();
        $pedido = $pedido->get($linea->idpedido);
        $stock = new \stock();
        $stock = $stock->get_by_referencia($linea->referencia, $pedido->codalmacen);
        return $stock;
    }

    public function set_reservado($c, $guardar = TRUE) {

        // Actualizar los reservados en el almacén
        $stock = $this->get_stock();
        $intentando = $c - $this->stockreservado;
        $cambio_final = $stock->sum_reservada($intentando);
        if($cambio_final != 0 && $stock->save()) {

            $this->stockreservado = ($cambio_final == $intentando) ? $c : $this->stockreservado + $cambio_final;
            if($guardar) {
                return $this->save();
            }
            return TRUE;
        }
        return FALSE;
    }

    public function eliminar_reserva() {
        if($this->set_reservado(0, FALSE) && $this->delete()) {
            return TRUE;
        }
        return FALSE;
    }

    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE " . $this->table_name . " SET stockreservado = " . $this->stockreservado
                . "  WHERE idlinea = " . $this->idlinea . ";";

            return $this->db->exec($sql);
        }

        $sql = "INSERT INTO " . $this->table_name . " (idlinea,stockreservado) VALUES (" 
            . $this->idlinea . ","
            . $this->stockreservado . ");";

        return $this->db->exec($sql);
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($this->idlinea) . ";");
    }
}
