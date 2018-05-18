<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/fbase_controller.php';

class ventas_articulos extends fbase_controller
{

    public $almacenes;
    public $b_bloqueados;
    public $b_codalmacen;
    public $b_codfabricante;
    public $b_codfamilia;
    public $b_codtarifa;
    public $b_constock;
    public $b_orden;
    public $b_publicos;
    public $b_subfamilias;
    public $b_url;
    public $familia;
    public $fabricante;
    public $impuesto;
    public $mostrar_tab_tarifas;
    public $offset;
    public $resultados;
    public $total_resultados;
    public $tarifa;
    public $transferencia_stock;
    public $atributos_familia;
    public $b_atributos_familia;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Artículos', 'ventas');
    }

    protected function private_core()
    {
        parent::private_core();

        $almacen = new almacen();
        $this->almacenes = $almacen->all();
        $articulo = new articulo();
        $this->familia = new familia();
        $this->fabricante = new fabricante();
        $this->impuesto = new impuesto();
        $this->tarifa = new tarifa();
        $this->transferencia_stock = new transferencia_stock();

        /**
         * Si hay alguna extensión de tipo config y texto no_tab_tarifas,
         * desactivamos la pestaña tarifas.
         */
        $this->mostrar_tab_tarifas = TRUE;
        foreach ($this->extensions as $ext) {
            if ($ext->type == 'config' && $ext->text == 'no_tab_tarifas') {
                $this->mostrar_tab_tarifas = FALSE;
                break;
            }
        }

        if (isset($_POST['codtarifa'])) {
            $this->edit_tarifa();
        } else if (isset($_GET['delete_tarifa'])) {
            $this->delete_tarifa();
        } else if (isset($_POST['referencia']) && isset($_POST['codfamilia']) && isset($_POST['codimpuesto'])) {
            $this->new_articulo($articulo);
            if(isset($_GET['new_articulo'])) {
                return;
            }
        } else if (isset($_GET['delete'])) {
            $this->delete_articulo($articulo);
        } else if (isset($_POST['origen'])) {
            $this->new_transferencia();
        } else if (isset($_GET['delete_transf'])) {
            $this->delete_transferencia($articulo);
        }

        $this->ini_filters();
        $this->search_articulos();
    }

    private function ini_filters()
    {
        $this->offset = 0;
        if (isset($_REQUEST['offset'])) {
            $this->offset = intval($_REQUEST['offset']);
        }

        $this->b_codalmacen = '';
        if (isset($_REQUEST['b_codalmacen'])) {
            $this->b_codalmacen = $_REQUEST['b_codalmacen'];
        }

        $this->b_codfamilia = '';
        $this->b_subfamilias = FALSE;
        if (isset($_REQUEST['b_codfamilia'])) {
            $this->b_codfamilia = $_REQUEST['b_codfamilia'];
            if ($_REQUEST['b_codfamilia']) {
                $this->b_subfamilias = isset($_REQUEST['b_subfamilias']);
            }
        }

        $this->b_codfabricante = '';
        if (isset($_REQUEST['b_codfabricante'])) {
            $this->b_codfabricante = $_REQUEST['b_codfabricante'];
        }

        $this->b_constock = isset($_REQUEST['b_constock']);
        $this->b_bloqueados = isset($_REQUEST['b_bloqueados']);
        $this->b_publicos = isset($_REQUEST['b_publicos']);

        $this->b_codtarifa = '';
        if (isset($_POST['b_codtarifa'])) {
            $this->b_codtarifa = $_POST['b_codtarifa'];
            setcookie('b_codtarifa', $this->b_codtarifa, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_GET['b_codtarifa'])) {
            $this->b_codtarifa = $_GET['b_codtarifa'];
            setcookie('b_codtarifa', $this->b_codtarifa, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['b_codtarifa'])) {
            $this->b_codtarifa = $_COOKIE['b_codtarifa'];
        }

        $this->b_orden = 'refmin';
        if (isset($_REQUEST['b_orden'])) {
            $this->b_orden = $_REQUEST['b_orden'];
            setcookie('ventas_articulos_orden', $this->b_orden, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['ventas_articulos_orden'])) {
            $this->b_orden = $_COOKIE['ventas_articulos_orden'];
        }

        $this->b_url = $this->url() . "&query=" . $this->query
            . "&b_codfabricante=" . $this->b_codfabricante
            . "&b_codalmacen=" . $this->b_codalmacen
            . "&b_codfamilia=" . $this->b_codfamilia
            . "&b_codtarifa=" . $this->b_codtarifa;

        if ($this->b_subfamilias) {
            $this->b_url .= '&b_subfamilias=TRUE';
        }

        if ($this->b_constock) {
            $this->b_url .= '&b_constock=TRUE';
        }

        if ($this->b_bloqueados) {
            $this->b_url .= '&b_bloqueados=TRUE';
        }

        if ($this->b_publicos) {
            $this->b_url .= '&b_publicos=TRUE';
        }

        if($this->b_codfamilia) {
            $atributo = new atributo_familia();
            $this->b_atributos_familia = array();
            $this->atributos_familia = $atributo->get_atributos_de_familia($this->b_codfamilia, TRUE);

            foreach($this->atributos_familia as $attr) {
                if($attr->tipo == 0) {
                    $min = isset($_REQUEST['b_'.$attr->idatributofamilia.'_min']) ? $_REQUEST['b_'.$attr->idatributofamilia.'_min'] : '';
                    $max = isset($_REQUEST['b_'.$attr->idatributofamilia.'_max']) ? $_REQUEST['b_'.$attr->idatributofamilia.'_max'] : '';
                    if(strlen($min) || strlen($max)) {
                        if(strlen($min)) {
                            $this->b_atributos_familia[$attr->idatributofamilia]['min'] = $min;
                            $this->b_url .= '&b_'.$attr->idatributofamilia.'_min='.$min;
                        }
                        if(strlen($max)) {
                            $this->b_atributos_familia[$attr->idatributofamilia]['max'] = $max;
                            $this->b_url .= '&b_'.$attr->idatributofamilia.'_max='.$max;
                        }
                    }
                }
                else {
                    $value = isset($_REQUEST['b_'.$attr->idatributofamilia]) ? $_REQUEST['b_'.$attr->idatributofamilia] : '';
                    if(strlen($value)) {
                        $this->b_atributos_familia[$attr->idatributofamilia] = $value;
                        $this->b_url .= '&b_'.$attr->idatributofamilia.'='.$value;
                    }
                }

            }
        }
        else {
            $this->atributos_familia = NULL;
        }
    }

    private function edit_tarifa()
    {
        $tar0 = $this->tarifa->get($_POST['codtarifa']);
        if (!$tar0) {
            $tar0 = new tarifa();
            $tar0->codtarifa = $_POST['codtarifa'];
        }
        $tar0->nombre = $_POST['nombre'];
        $tar0->aplicar_a = $_POST['aplicar_a'];
        $tar0->set_x(floatval($_POST['dtopor']));
        $tar0->set_y(floatval($_POST['inclineal']));
        $tar0->mincoste = isset($_POST['mincoste']);
        $tar0->maxpvp = isset($_POST['maxpvp']);
        if ($tar0->save()) {
            $this->new_message("Tarifa guardada correctamente.");
        } else {
            $this->new_error_msg("¡Imposible guardar la tarifa!");
        }
    }

    private function delete_tarifa()
    {
        $tar0 = $this->tarifa->get($_GET['delete_tarifa']);
        if ($tar0) {
            if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($tar0->delete()) {
                $this->new_message("Tarifa " . $tar0->codtarifa . " eliminada correctamente.", TRUE);
            } else {
                $this->new_error_msg("¡Imposible eliminar la tarifa!");
            }
        } else {
            $this->new_error_msg("¡La tarifa no existe!");
        }
    }

    private function new_articulo(&$articulo)
    {
        $this->save_codimpuesto($_POST['codimpuesto']);

        if ($_POST['referencia'] == '') {
            $referencia = $articulo->get_new_referencia();
        } else {
            $referencia = $_POST['referencia'];
        }

        $result = array();
        $art0 = $articulo->get($referencia);
        if ($art0) {
            if(isset($_GET['new_articulo'])) {
                $result[] = $art0;
            }
            else {
                $this->new_error_msg('Ya existe el artículo <a href="' . $art0->url() . '">' . $art0->referencia . '</a>');
            }
        } else {
            $articulo->referencia = $referencia;
            $articulo->descripcion = $_POST['descripcion'];
            $articulo->nostock = isset($_POST['nostock']);

            if ($_POST['codfamilia'] != '') {
                $articulo->codfamilia = $_POST['codfamilia'];
            }

            if ($_POST['codfabricante'] != '') {
                $articulo->codfabricante = $_POST['codfabricante'];
            }

            $articulo->set_impuesto($_POST['codimpuesto']);

            $pvp = floatval(str_replace(',', '.', $_POST['pvp']));
            if (isset($_POST['coniva'])) {
                $articulo->set_pvp_iva($pvp);
            } else {
                $articulo->set_pvp($pvp);
            }

            if(isset($_GET['new_articulo'])) {
                $articulo->secompra = isset($_POST['secompra']);
                $articulo->sevende = isset($_POST['sevende']);
                $articulo->publico = isset($_POST['publico']);
            }

            // Si es un artículo incluido desde compra con el coste
            if(isset($_POST["coste"])) {
                $articulo->costemedio = floatval($_POST['coste']);
                $articulo->preciocoste = floatval($_POST['coste']);
            }

            // Si es un artículo incluido desde compra con la referencia del proveedor
            if(isset($_POST['refproveedor']) && $_POST['refproveedor'] != '' && $_POST['refproveedor'] != $_POST['referencia']) {
                $articulo->equivalencia = $_POST['refproveedor'];
            }

            if ($articulo->save()) {
                if(isset($_POST["coste"])) {
                    $articulo->coste = floatval($_POST['coste']);
                    $articulo->dtopor = 0;
                }

                // Si es un artículo incluido desde compra se añade el artículo del proveedor
                if(isset($_POST['codproveedor'])) {
                    $articulo->coste = floatval($_POST['coste']);
                    $articulo->dtopor = 0;
    
                    /// buscamos y guardamos el artículo del proveedor
                    $ap = new articulo_proveedor();
                    $ap = $ap->get_by($articulo->referencia, $_POST['codproveedor'], $_POST['refproveedor']);
                    if ($ap) {
                        $articulo->coste = $ap->precio;
                        $articulo->dtopor = $ap->dto;
                    } else {
                        $ap = new articulo_proveedor();
                        $ap->codproveedor = $_POST['codproveedor'];
                    }
                    $ap->referencia = $articulo->referencia;
                    $ap->refproveedor = $_POST['refproveedor'];
                    $ap->descripcion = $articulo->descripcion;
                    $ap->codimpuesto = $articulo->codimpuesto;
                    $ap->precio = floatval($_POST['coste']);
    
                    if($_POST['refproveedor'] != '') {
                        $ap->save();
                    }
                }

                // Almacenar los atributos de la familia
                $atributo = new atributo_familia();
                $atributos = $atributo->get_atributos_de_familia($articulo->codfamilia);
                foreach($atributos as $atr) {
                    if(!isset($_POST["pordefecto_" . $atr->idatributofamilia])) {
                        $this->new_error_msg("Error al añadir el atributo ". $atr->nombre." al artículo");
                    }
                    $valor = $_POST["pordefecto_" . $atr->idatributofamilia];
                    $dato = new atributo_articulo_familia(array("referencia"=>$articulo->referencia,"idatributofamilia"=>$atr->idatributofamilia,"valor"=>$valor));
                    $dato->save();
                }

                if(isset($_GET['new_articulo'])) {
                    $result[] = $articulo;
                }
                else {
                    header('location: ' . $articulo->url());
                }
            } else {
                $this->new_error_msg("¡Error al crear el articulo!");
            }
        }

        if(isset($_GET['new_articulo'])) {
            $this->template = FALSE;
            header('Content-Type: application/json');
            echo json_encode($result);
        }

    }

    private function delete_articulo(&$articulo)
    {
        $art = $articulo->get($_GET['delete']);
        if ($art) {
            if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($art->delete()) {
                $this->new_message("Articulo " . $art->referencia . " eliminado correctamente.", TRUE);
            } else {
                $this->new_error_msg("¡Error al eliminar el articulo!");
            }
        } else {
            $this->new_error_msg("Articulo no encontrado.");
        }
    }

    private function new_transferencia()
    {
        $this->transferencia_stock->usuario = $this->user->nick;
        $this->transferencia_stock->codalmaorigen = $_POST['origen'];
        $this->transferencia_stock->codalmadestino = $_POST['destino'];

        if ($this->transferencia_stock->save()) {
            $this->new_message('Datos guardados correctamente.');
            header('Location: ' . $this->transferencia_stock->url());
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }

    private function delete_transferencia(&$articulo)
    {
        $transf = $this->transferencia_stock->get($_GET['delete_transf']);

        if (!$this->allow_delete) {
            $this->new_error_msg('No tienes permiso para eliminar en esta página.');
        } else if ($transf) {
            $ok = TRUE;

            /// eliminamos las líneas
            $ltf = new linea_transferencia_stock();
            foreach ($ltf->all_from_transferencia($transf->idtrans) as $lin) {
                if ($lin->delete()) {
                    /// movemos el stock
                    $art = $articulo->get($lin->referencia);
                    if ($art) {
                        $art->sum_stock($transf->codalmadestino, 0 - $lin->cantidad);
                        $art->sum_stock($transf->codalmaorigen, $lin->cantidad);
                    }
                } else {
                    $this->new_error_msg('Error al eliminar la línea con referencia ' . $lin->referencia);
                    $ok = FALSE;
                }
            }

            if ($ok) {
                if ($transf->delete()) {
                    $this->new_message('Transferencia eliminada correctamente.');
                } else {
                    $this->new_error_msg('Error al eliminar la transferencia.');
                }
            }
        } else {
            $this->new_error_msg('Transferencia no encontrada.');
        }
    }

    private function search_articulos()
    {
        $this->resultados = array();
        $this->num_resultados = 0;
        $articulo = new articulo();

        if(!method_exists($articulo,"search_con_atributos")) {
            return;
        }

        $sql = $articulo->search_con_atributos($this->query, 0, $this->b_codfamilia, $this->b_constock, $this->b_codfabricante, $this->b_bloqueados, '', $this->b_atributos_familia, TRUE, $this->b_subfamilias, $this->b_codalmacen, $this->b_publicos);

        $order = 'referencia DESC';
        switch ($this->b_orden) {
            case 'stockmin':
                $order = 'stockfis ASC';
                break;

            case 'stockmax':
                $order = 'stockfis DESC';
                break;

            case 'refmax':
                if (strtolower(FS_DB_TYPE) == 'postgresql') {
                    $order = 'a.referencia DESC';
                } else {
                    $order = 'lower(a.referencia) DESC';
                }
                break;

            case 'descmin':
                $order = 'descripcion ASC';
                break;

            case 'descmax':
                $order = 'descripcion DESC';
                break;

            case 'preciomin':
                $order = 'pvp ASC';
                break;

            case 'preciomax':
                $order = 'pvp DESC';
                break;

            default:
            case 'refmin':
                if (strtolower(FS_DB_TYPE) == 'postgresql') {
                    $order = 'a.referencia ASC';
                } else {
                    $order = 'lower(a.referencia) ASC';
                }
                break;
        }

        $data = $this->db->select("SELECT COUNT(a.referencia) as total" . $sql);
        if ($data) {
            $this->total_resultados = intval($data[0]['total']);

            /// ¿Descargar o mostrar en pantalla?
            if (isset($_GET['download'])) {
                $this->download_resultados($sql, $order);
            } else {
                $data2 = $this->db->select_limit("SELECT a.*" . $sql . " ORDER BY " . $order, FS_ITEM_LIMIT, $this->offset);
                if ($data2) {
                    foreach ($data2 as $i) {
                        $this->resultados[] = new articulo($i);
                    }

                    if ($this->b_codalmacen != '') {
                        /// obtenemos el stock correcto
                        foreach ($this->resultados as $i => $value) {
                            $this->resultados[$i]->stockfis = 0;
                            foreach ($value->get_stock() as $s) {
                                if ($s->codalmacen == $this->b_codalmacen) {
                                    $this->resultados[$i]->stockfis = $s->cantidad;
                                }
                            }
                        }
                    }

                    if ($this->b_codtarifa != '') {
                        /// aplicamos la tarifa
                        $tarifa = $this->tarifa->get($this->b_codtarifa);
                        if ($tarifa) {
                            $tarifa->set_precios($this->resultados);

                            /// si la tarifa añade descuento, lo aplicamos al precio
                            foreach ($this->resultados as $i => $value) {
                                $this->resultados[$i]->pvp -= $value->pvp * $value->dtopor / 100;
                            }
                        }
                    }

                    $articulo = new articulo();
                    if(method_exists($articulo, 'agnadir_atributos')) {
                        $articulo->agnadir_atributos($this->resultados);
                    }
                }
            }
        }
    }

    private function download_resultados($sql, $order)
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"articulos.csv\"");
        echo "referencia;codfamilia;codfabricante;descripcion;pvp;iva;codbarras;stock;coste\n";

        $offset2 = 0;
        $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $order, 1000, $offset2);
        while ($data2) {
            $resultados = array();
            foreach ($data2 as $i) {
                $resultados[] = new articulo($i);
            }

            if ($this->b_codalmacen != '') {
                /// obtenemos el stock correcto
                foreach ($resultados as $i => $value) {
                    $resultados[$i]->stockfis = 0;
                    foreach ($value->get_stock() as $s) {
                        if ($s->codalmacen == $this->b_codalmacen) {
                            $resultados[$i]->stockfis = $s->cantidad;
                        }
                    }
                }
            }

            if ($this->b_codtarifa != '') {
                /// aplicamos la tarifa
                $tarifa = $this->tarifa->get($this->b_codtarifa);
                if ($tarifa) {
                    $tarifa->set_precios($resultados);

                    /// si la tarifa añade descuento, lo aplicamos al precio
                    foreach ($resultados as $i => $value) {
                        $resultados[$i]->pvp -= $value->pvp * $value->dtopor / 100;
                    }
                }
            }

            /**
             * libreoffice y excel toman el punto y 3 decimales como millares,
             * así que si el usuario ha elegido 3 decimales, mejor usamos 4.
             */
            $nf0 = FS_NF0_ART;
            if ($nf0 == 3) {
                $nf0 = 4;
            }

            /// escribimos los datos de los artículos
            foreach ($resultados as $art) {
                echo $art->referencia . ';';
                echo $art->codfamilia . ';';
                echo $art->codfabricante . ';';
                echo fs_fix_html(preg_replace('~[\r\n]+~', ' ', $art->descripcion)) . ';';
                echo number_format($art->pvp, $nf0, FS_NF1, '') . ';';
                echo number_format($art->get_iva(), 2, FS_NF1, '') . ';';
                echo trim($art->codbarras) . ';';
                echo number_format($art->stockfis, 2, FS_NF1, '') . ';';
                echo number_format($art->preciocoste(), $nf0, FS_NF1, '') . "\n";

                $offset2++;
            }

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $order, 1000, $offset2);
        }
    }

    public function paginas()
    {
        $url = $this->b_url . '&b_orden=' . $this->b_orden;
        return $this->fbase_paginas($url, $this->total_resultados, $this->offset);
    }

    private function get_subfamilias($cod)
    {
        $familias = array($cod);

        $data = $this->db->select("SELECT codfamilia,madre FROM familias WHERE madre = " . $this->empresa->var2str($cod) . ";");
        if ($data) {
            foreach ($data as $d) {
                foreach ($this->get_subfamilias($d['codfamilia']) as $subf) {
                    $familias[] = $subf;
                }
            }
        }

        return $familias;
    }
}
