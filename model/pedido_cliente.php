<?php
/*
 * This file is part of presupuestos_y_pedidos
 * Copyright (C) 2016-2017 Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'plugins/facturacion_base/model/core/pedido_cliente.php';

/**
 * Pedido de cliente
 */
class pedido_cliente extends FacturaScripts\model\pedido_cliente
{
    /**
     * Elimina el pedido de la base de datos.
     * Devuelve FALSE en caso de fallo.
     * @return boolean
     */
    public function delete()
    {
        // Eliminamos los artículos reservados en el pedido
        $linea = new linea_pedido_cliente();
        $lineas = $linea->all_from_pedido($this->idpedido);
        $linea_reserva = new linea_pedido_cliente_reservado();
        foreach($lineas as $linea) {
            $reserva = $linea_reserva->get($linea->idlinea);
            if($reserva) {
                $reserva->eliminar_reserva();
            }
        }

        if ($this->db->exec("DELETE FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($this->idpedido) . ";")) {
            /// modificamos el presupuesto relacionado
            $this->db->exec("UPDATE presupuestoscli SET idpedido = NULL, editable = TRUE,"
                . " status = 0 WHERE idpedido = " . $this->var2str($this->idpedido) . ";");

            $this->new_message(ucfirst(FS_PEDIDO) . ' de venta ' . $this->codigo . " eliminado correctamente.");
            return TRUE;
        }

        return FALSE;
    }
}
