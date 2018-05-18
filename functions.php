<?php
// Encontrar un valor mediante una función predicado. Devuelve el valor si fue encontrado o null en caso contrario.
function array_find($array, $predicado)
{
    foreach($array as $e) {
        if($predicado($e)) {
            return $e;
        }
    }
    return NULL;
};

function _strlen($s)
{
    return mb_strlen($s, 'utf8');
}

function crear_objeto_filtraje_por_atributos_familia()
{
    $result = array();
    if($_GET["codfamilia"]) {
        $atributo = new atributo_familia();
        $atributos = $atributo->get_atributos_de_familia($_GET["codfamilia"]);

        if($atributos) {
            foreach($atributos as $atributo) {
                if($atributo->tipo == 0) {
                    if(isset($_GET['b_'.$atributo->idatributofamilia.'_min']) && $_GET['b_'.$atributo->idatributofamilia.'_min'] !== "") {
                        $result[$atributo->idatributofamilia]['min'] = $_GET['b_'.$atributo->idatributofamilia.'_min'];
                    }
                    if(isset($_GET['b_'.$atributo->idatributofamilia.'_max']) && $_GET['b_'.$atributo->idatributofamilia.'_max'] !== "") {
                        $result[$atributo->idatributofamilia]['max'] = $_GET['b_'.$atributo->idatributofamilia.'_max'];
                    }
                }
                else {
                    if(isset($_GET['b_'.$atributo->idatributofamilia]) && $_GET['b_'.$atributo->idatributofamilia] !== "") {
                        $result[$atributo->idatributofamilia] = $_GET['b_'.$atributo->idatributofamilia];
                    }
                }
            }
        }

    }

    return count($result) ? $result : FALSE;
}

/*
$referencias = implode(",", array_map(function($v) {
                return $this->var2str($v->referencia);
            }, $results));

            $sql = 'SELECT referencia FROM articulos NATURAL LEFT JOIN atributosarticulosfamilia WHERE referencia IN ('.$referencias.') AND ';

            $where = array();
            foreach($atributos as $atributo) {
                if($atributo->tipo == 0) {
                    $subwhere = array();
                    if(isset($_GET['b_'.$atributo->idatributofamilia.'_min'])) {
                        $subwhere[] = "valor >= ". $_GET['b_'.$atributo->idatributofamilia.'_min'];
                    }
                    if(isset($_GET['b_'.$atributo->idatributofamilia.'_max'])) {
                        $subwhere[] = "valor <= ". $_GET['b_'.$atributo->idatributofamilia.'_max'];
                    }
                    $where[] = "(idatributofamilia = ".$atributo->idatributofamilia." AND ".implode(' AND ', $subwhere).")";
                }
                else {
                    if(isset($_GET['b_'.$atributo->idatributofamilia])) {
                        $where[] = "(idatributofamilia = ".$atributo->idatributofamilia." AND valor = ".$_GET['b_'.$atributo->idatributofamilia].")";
                    }
                }
            }
            if(count($where)) {
                $sql .= "(" .implode(" OR ", $subwhere) . ")";

                $data = $db->select($sql);

                $results = array_filter($results, function($v) use ($data) {
                    return in_array($v->referencia, $data);
                });
            }

*/