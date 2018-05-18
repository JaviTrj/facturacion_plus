<?php

use FacturaScripts\model\pendiente_proveedor;
/*
 * This file is part of facturacion_base
 * Copyright (C) 2012-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/model/core/articulo.php';

/**
 * Almacena los datos de un artículo.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class articulo extends FacturaScripts\model\articulo
{
    
    static protected $initialized = FALSE;

    protected function init()
    {   
        if(self::$initialized) {
            return;
        }

        // Requerido para save_many
        new atributo_articulo_familia();

        // Requeridos para pendientes de proveedor
        new linea_pedido_cliente();
        new stock();
        new pendiente_proveedor();
        new linea_pedido_proveedor();

        self::$initialized = TRUE;
    }

    protected function get_sql_para_articulos_familia_y_descendientes($cod)
    {
        $familia = new familia();
        $conjunto_familias = $familia->get_lista_hijas($cod);
        $conjunto_familias[] = $cod;
        $conjunto_familias = implode(", ", array_map(function($v) {
            return $this->var2str($v);
        }, $conjunto_familias));
        return "FROM " . $this->table_name . " WHERE codfamilia IN ("
        . $conjunto_familias . ") ORDER BY codfamilia != ". $this->var2str($cod) .",codfamilia,lower(referencia) ASC";
    }

    public function get_referencia_de_articulos_de_familia($codfamilia)
    {
        $data = $this->db->select("SELECT referencia ".$this->get_sql_para_articulos_familia_y_descendientes($codfamilia));
        return array_map(function($v) {
            return $v["referencia"];
        }, $data);
    }

    public function all_from_familia($cod, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * ".$this->get_sql_para_articulos_familia_y_descendientes($cod);

        $result = $this->all_from($sql, $offset, $limit);


        $familia = new familia();
        return array_map(function($v) use($familia) {
            // Añadir el nombre de la familia al articulo
            $v->familia = $familia->get($v->codfamilia)->descripcion;
            return $v;
        }, $result);
    }

    // Solamente copiada porque es privada
    protected function all_from($sql, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $artilist = array();
        $data = $this->db->select_limit($sql, $limit, $offset);
        if ($data) {
            foreach ($data as $a) {
                $artilist[] = new \articulo($a);
            }
        }

        return $artilist;
    }

    public function save_many($articulos)
    {
        $insert = "INSERT INTO " . $this->table_name . " (referencia,codfamilia,codfabricante,descripcion,pvp,preciocoste," .
            "codimpuesto,nostock,secompra,sevende,codbarras,observaciones,publico) VALUES ";
        $sql = "";
        foreach($articulos as $articulo) {
            $sql .= $insert . "(" .
                $this->var2str($articulo->referencia) . "," .
                $this->var2str($articulo->codfamilia) . "," .
                $this->var2str($articulo->codfabricante) . "," .
                $this->var2str($articulo->descripcion) . "," .
                $this->var2str($articulo->pvp) . "," .
                $this->var2str($articulo->preciocoste) . "," .
                $this->var2str($articulo->codimpuesto) . "," .
                $this->var2str($articulo->nostock) . "," .
                $this->var2str($articulo->secompra) . "," .
                $this->var2str($articulo->sevende) . "," .
                $this->var2str($articulo->codbarras) . "," .
                $this->var2str($articulo->observaciones) . "," .
                $this->var2str($articulo->publico) . ")";
            $insert = ",";
        }
        $sql .= ";";

        $this->init();

        $insert = "INSERT INTO atributosarticulosfamilia (idatributofamilia,referencia,valor) VALUES ";
        foreach($articulos as $articulo) {
            foreach($articulo->atributos as $idatributo=>$valor) {
                $sql .= $insert . "(" . $idatributo .
                    "," . $this->var2str($articulo->referencia) .
                    "," . $valor . ")";
                $insert = ",";
            }
        }
        $sql .= ";";

        return $this->db->exec($sql);
    }

    public function delete_many($referencias)
    {
        $referencias = implode(",", array_map(function($v) { return $this->var2str($v); }, $referencias));
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE referencia IN (" . $referencias . ");");
    }

    // Dado una serie de articulos, les añade a cada uno un array con sus atributos
    public function agnadir_atributos(&$articulos)
    {
        if(count($articulos) == 0) {
            return;
        }

        $valor = new atributo_articulo_familia();
        $valores = $valor->get_valores_articulos($articulos);
        
        if($valores) {
            foreach($articulos as &$articulo) {
                $articulo->atributos = array_filter($valores, function($v) use(&$articulo) {
                    return $v['referencia'] == $articulo->referencia;
                });
            }
        }
    }

    public function test_atributos($lista_atributos) {
        if(!count($lista_atributos)) {
            return TRUE;
        }
        if(is_null($this->codfamilia)) {
            return FALSE;
        }
        $atributo_familia = new atributo_familia();
        $atributos_familia = $atributo_familia->get_atributos_de_familia($this->codfamilia,TRUE);

        foreach($atributos_familia as $atr) {
            if(!isset($lista_atributos[$atr->idatributofamilia])) {
                if(!$atr->permitirnulo) {
                    $this->new_error_msg("Atributo " . $atr->nombre . " no permite nulos y no está definido para este artículo");
                    return FALSE;
                }
                continue;
            } else {
                $valor = $lista_atributos[$atr->idatributofamilia];
                // Si es de tipo valores revisamos que el que vamos a poner existe
                if($atr->tipo === 2 && is_null(array_find($atr->valores,function($v) use($valor) { return $v->idvaloratributo === $valor; }))) {
                    $this->new_error_msg("Valor " . $valor . " de atributo " . $atr->nombre . " no existe");
                    return FALSE;
                }

                unset($lista_atributos[$atr->idatributofamilia]);
            }
        }

        $status = TRUE;

        foreach($lista_atributos as $idatributo=>$valor) {
            $this->new_error_msg("Se especifica un valor de atributo " . $idatributo . " cuando no es parte de la familia " . $this->codfamilia);            
            $status = FALSE;
        }

        return $status;
    }

    public function pendientes_de_proveedor($codproveedor, $codalmacen = NULL, $pisar_pendientes = FALSE)
    {
        $this->init();

        $sql = "SELECT a.*,pedido.cantidad as pedidos, IFNULL(pedido_compra.cantidad,0) as pedidos_compra, IFNULL(pendiente.cantidad,0) as pendientes_proveedor, IFNULL(almacenado.cantidad,0) as almacenados FROM " . $this->table_name . " a ".
            "JOIN fabricantes f ON a.codfabricante = f.codfabricante AND f.asgproveedor = ".$this->var2str($codproveedor)." AND a.nostock = FALSE AND a.tipo is NULL ".
            "LEFT JOIN (SELECT SUM(cantidad) as cantidad,referencia FROM lineaspedidoscli ln JOIN pedidoscli pd ON ln.idpedido = pd.idpedido ".($codalmacen ? "AND pd.codalmacen = ".$this->var2str($codalmacen) : "")." AND pd.status = 0 GROUP BY referencia) pedido ON pedido.referencia = a.referencia ".
            "LEFT JOIN (SELECT SUM(cantidad) as cantidad,referencia FROM stocks ".($codalmacen ? "WHERE stocks.codalmacen = ".$this->var2str($codalmacen) : "")." GROUP BY referencia) almacenado ON almacenado.referencia = a.referencia ".
            "LEFT JOIN (SELECT SUM(cantidad) as cantidad,referencia FROM pendientesproveedores pdt JOIN albaranesprov alb ON pdt.idalbaran = alb.idalbaran ".($codalmacen ? "AND alb.codalmacen = ".$this->var2str($codalmacen) : "")." AND alb.codproveedor = ".$this->var2str($codproveedor)." GROUP BY referencia) pendiente ON pendiente.referencia = a.referencia ".
            "LEFT JOIN (SELECT SUM(cantidad) as cantidad,referencia FROM lineaspedidosprov lnp JOIN pedidosprov pdv ON lnp.idpedido = pdv.idpedido ".($codalmacen ? "AND pdv.codalmacen = ".$this->var2str($codalmacen) : "")." AND pdv.editable = TRUE GROUP BY referencia) pedido_compra ON pedido_compra.referencia = a.referencia;";

        $data = $this->db->select($sql);
        if ($data) {
            $artlist = array();
            foreach ($data as $d) {
                $articulo = new \articulo($d);
                $articulo->pendientes = $d['pedidos'] - $d['pedidos_compra'] - $d['almacenados'];
                if($d['pendientes_proveedor'] > 0) {
                    if($pisar_pendientes && $articulo->pendientes - $d['pendientes_proveedor'] > 0) {
                        $articulo->pendientes_proveedor = $d['pendientes_proveedor'];
                    }
                    else {
                        $articulo->pendientes -= $d['pendientes_proveedor'];
                    }
                }
                if($articulo->pendientes <= 0) {
                    continue;
                }
                $artlist[] = $articulo;
            }
            return $artlist;
        }

        return FALSE;
    }

    public function search_con_atributos($query = '', $offset = 0, $codfamilia = '', $con_stock = FALSE, $codfabricante = '', $bloqueados = FALSE, $codproveedor = '', $atributos = FALSE, $return_sql = FALSE, $subfamilias = FALSE, $codalmacen = '', $publicos = FALSE)
    {
        $artilist = array();
        $query = $this->no_html(mb_strtolower($query, 'UTF8'));

        $sql = " FROM " . $this->table_name . " a";
        $separador = ' WHERE';
        $endsql = "";

        if($codproveedor) {
            $sql .= " JOIN articulosprov ap ON a.referencia = ap.referencia AND ap.codproveedor = " . $this->var2str($codproveedor);
        }

        if($atributos) {
            $subwhere = array();
            $are_nulls = FALSE;
            $notnulls = array();
            foreach($atributos as $idatributofamilia=>$values) {
                if(is_array($values)) {
                    $subsubwhere = array();
                    if(array_key_exists('min', $values)) {
                        $subsubwhere[] = "atr.valor >= ".$values['min'];
                    }
                    if(array_key_exists('max', $values)) {
                        $subsubwhere[] = "atr.valor <= ".$values['max'];
                    }
                    $subwhere[] = "(atr.idatributofamilia = ".$idatributofamilia." AND ".implode(' AND ', $subsubwhere).")";
                    $notnulls[] = $idatributofamilia;
                }
                else {
                    if($values == "-1") {
                        $are_nulls = TRUE;
                        $subwhere[] = "(atr.idatributofamilia = ".$idatributofamilia.")";
                    }
                    else {
                        // Si es de tipo valores y se están filtrando con varios se puede dividir por comas
                        $values = explode(",", $values);
                        $subsubwhere = "(atr.idatributofamilia = ".$idatributofamilia." AND ";
                        if(count($values) > 1) {
                            $subsubwhere .= "(" . implode(" OR ", array_map(function($v) {
                                return "atr.valor = ".$v;
                            }, $values)) . ")";
                        }
                        else {
                            $subsubwhere .= "atr.valor = ".$values[0];
                        }
                        $subwhere[] = $subsubwhere . ")";
                        $notnulls[] = $idatributofamilia;
                    }
                }
            }
            if($are_nulls || count($notnulls)) {

                $subsql = " JOIN atributosarticulosfamilia atr ON a.referencia = atr.referencia AND (" .implode(" OR ", $subwhere) . ")";
                $endsql = ' GROUP BY a.referencia'.(count($notnulls) > 1 || $are_nulls ? ' HAVING COUNT(*) = ' . max(count($notnulls),1) : '');

                if($are_nulls) {
                    if(count($notnulls)) {
                        $endsql .= " AND (" . implode(" OR ", array_map(function($v) {
                            return "FIND_IN_SET(" . $v . ",GROUP_CONCAT(atr.idatributofamilia))"; }, $notnulls)) . ")";
                    }
                    else {
                        $subsql = " LEFT" . $subsql . " WHERE atr.idatributofamilia IS NULL"; 
                        $separador = ' AND';
                    }
                }

                $sql .= $subsql;
            }
        }

        if ($codfamilia) {
            if ($subfamilias) {
                $familia = new familia();
                $sql .= $separador . "codfamilia IN (";
                $coma = '';
                foreach ($this->get_lista_hijas($codfamilia) as $fam) {
                    $sql .= $coma . $this->var2str($fam);
                    $coma = ',';
                }
                $sql .= ")";
            } else {
                $sql .= $separador . " a.codfamilia = " . $this->var2str($codfamilia);
            }
            $separador = ' AND';
        }

        if ($codfabricante) {
            $sql .= $separador . " a.codfabricante = " . $this->var2str($codfabricante);
            $separador = ' AND';
        }

        if ($con_stock) {
            if ($codalmacen == '') {
                $sql .= $separador . "a.stockfis > 0";
            } else {
                $sql .= $separador . "a.referencia IN (SELECT referencia FROM stocks WHERE cantidad > 0"
                    . " AND a.codalmacen = " . $this->empresa->var2str($this->b_codalmacen) . ')';
            }
            $separador = ' AND';
        }

        if ($bloqueados) {
            $sql .= $separador . " a.bloqueado = TRUE";
            $separador = ' AND';
        } else {
            $sql .= $separador . " a.bloqueado = FALSE";
            $separador = ' AND';
        }

        if ($publicos) {
            $sql .= $separador . "a.publico = TRUE";
            $separador = ' AND ';
        }

        if ($query == '') {
            /// nada
        } else if (is_numeric($query)) {
            $sql .= $separador . " (a.referencia = " . $this->var2str($query)
                . " OR a.referencia LIKE '%" . $query . "%'"
                . " OR a.partnumber LIKE '%" . $query . "%'"
                . " OR a.equivalencia LIKE '%" . $query . "%'"
                . " OR a.descripcion LIKE '%" . $query . "%'"
                . " OR a.codbarras = " . $this->var2str($query) . ")";
        } else {
            /// ¿La búsqueda son varias palabras?
            $palabras = explode(' ', $query);
            if (count($palabras) > 1) {
                $sql .= $separador . " (lower(a.referencia) = " . $this->var2str($query)
                    . " OR lower(a.referencia) LIKE '%" . $query . "%'"
                    . " OR lower(a.partnumber) LIKE '%" . $query . "%'"
                    . " OR lower(a.equivalencia) LIKE '%" . $query . "%'"
                    . " OR (";

                foreach ($palabras as $i => $pal) {
                    if ($i == 0) {
                        $sql .= "lower(a.descripcion) LIKE '%" . $pal . "%'";
                    } else {
                        $sql .= " AND lower(a.descripcion) LIKE '%" . $pal . "%'";
                    }
                }

                $sql .= "))";
            } else {
                $sql .= $separador . " (lower(a.referencia) = " . $this->var2str($query)
                    . " OR lower(a.referencia) LIKE '%" . $query . "%'"
                    . " OR lower(a.partnumber) LIKE '%" . $query . "%'"
                    . " OR lower(a.equivalencia) LIKE '%" . $query . "%'"
                    . " OR lower(a.codbarras) = " . $this->var2str($query)
                    . " OR lower(a.descripcion) LIKE '%" . $query . "%')";
            }
        }

        $sql .= $endsql;

        if($return_sql) {
            return $sql;
        }

        $column_list = 'a.referencia,a.codfamilia,a.codfabricante,a.descripcion,a.pvp,a.factualizado,a.costemedio,' .
                'a.preciocoste,a.codimpuesto,a.stockfis,a.stockmin,a.stockmax,a.controlstock,a.nostock,a.bloqueado,' .
                'a.secompra,a.sevende,a.equivalencia,a.codbarras,a.observaciones,a.imagen,a.publico,a.tipo,' .
                'a.partnumber,a.codsubcuentacom,a.codsubcuentairpfcom,a.trazabilidad';
        $sql = "SELECT " . $column_list . $sql;

        if (strtolower(FS_DB_TYPE) == 'mysql') {
            $sql .= " ORDER BY lower(a.referencia) ASC";
        } else {
            $sql .= " ORDER BY a.referencia ASC";
        }

        return $this->all_from($sql, $offset);
    }
}
