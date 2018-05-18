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

class atributo_familia extends \fs_model
{

    public $codfamilia;
    public $idatributofamilia;
    public $nombre;
    public $tipo;
    public $permitirnulo;

    public function __construct($data = FALSE)
    {
        parent::__construct('atributosfamilia');
        if ($data) {
            $this->codfamilia = $data['codfamilia'];
            $this->idatributofamilia = array_key_exists('idatributofamilia', $data) ? $data['idatributofamilia'] : NULL;
            $this->nombre = $data['nombre'];
            $this->tipo = strlen($data['tipo']) == 1 ? (int)$data['tipo'] : $this->tipo2integer($data['tipo']);
            $this->permitirnulo = isset($data['permitirnulo']) && ($data['permitirnulo'] == 'TRUE' || $data['permitirnulo'] == '1');
        } else {
            $this->codfamilia = NULL;
            $this->idatributofamilia = NULL;
            $this->nombre = '';
            $this->tipo = NULL;
            $this->permitirnulo = NULL;
        }
    }

    public function exists()
    {
        if (is_null($this->idatributofamilia)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idatributofamilia = " . $this->idatributofamilia . ";");
    }

    public function tipo2integer($tipo)
    {
        switch($tipo) {
            case 'number':
                return 0;
            case 'boolean':
                return 1;
            case 'values':
                return 2;
            default:
                $this->new_error_msg("Tipo de atributo de familia invalido");
                return NULL;
        }
    }

    public function get_tipo_name($integer)
    {
        switch($integer) {
            case 0:
                return 'Número';
            case 1:
                return 'Binario';
            case 2:
                return 'Valores';
            default:
                $this->new_error_msg("Número del Tipo de atributo de familia invalido");
                return NULL;
        }
    }

    public function get($idatributofamilia)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idatributofamilia = " . $idatributofamilia . ";");
        if($data) {
            return new \atributo_familia($data[0]);
        }

        return FALSE;
    }

    public function test()
    {
        $status = FALSE;

        $this->codfamilia = $this->no_html($this->codfamilia);
        $this->nombre = $this->no_html($this->nombre);

        if (strlen($this->codfamilia) < 1 || strlen($this->codfamilia) > 8) {
            $this->new_error_msg("Código de familia no válido. Deben ser entre 1 y 8 caracteres.");
        } else if (strlen($this->nombre) < 1 || strlen($this->nombre) > 20) {
            $this->new_error_msg("Nombre de atributo de familia no válida.");
        } else if(!is_null($this->tipo) && is_bool($this->permitirnulo)) {
            $status = TRUE;
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                return $this->db->exec("UPDATE " . $this->table_name . " SET codfamilia = " . $this->var2str($this->codfamilia) .
                    ", nombre = " . $this->var2str($this->nombre) .
                    ", tipo = " . $this->tipo .
                    ", permitirnulo = " . (int)$this->permitirnulo .
                    " WHERE idatributofamilia = " . $this->idatributofamilia . ";");
            }
            
            $sql = "INSERT INTO " . $this->table_name . " (codfamilia,nombre,tipo,permitirnulo) VALUES " .
                    "(" . $this->var2str($this->codfamilia) .
                    "," . $this->var2str($this->nombre) .
                    "," . $this->tipo .
                    "," . (int)$this->permitirnulo . ");";
            
            if($this->db->exec($sql)) {
                $this->idatributofamilia = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    public function delete()
    {
        $this->clean_cache();

        $sql = "DELETE FROM " . $this->table_name . " WHERE idatributofamilia = " . $this->idatributofamilia . ";";
        return $this->db->exec($sql);
    }

    public function clean_cache($codfamilia = NULL) {
        if(!$codfamilia) {
            $codfamilia = $this->codfamilia;
        }

        $this->cache->delete('m_atributos_familia_all');
        $this->cache->delete('m_atributos_familia_'.$codfamilia);
        // Limpiar la cache de todas las familias hijas
        $familia = new \familia();
        $hijas = $familia->get_lista_hijas($this->codfamilia);
        foreach($hijas as $hija) {
            $this->cache->delete('m_atributos_familia_'.$hija);
        }
    }

    public function diff_atributos_cambio_familia($nueva_familia, $vieja_familia, $add_valores = FALSE)
    {
        $atributos_nuevos = $nueva_familia ? $this->get_atributos_de_familia($nueva_familia, $add_valores) : array();
        $atributos_perdidos = $vieja_familia ? $this->get_atributos_de_familia($vieja_familia, $add_valores) : array();

        function comparator ($a,$b) { 
            return $a->idatributofamilia - $b->idatributofamilia;
        };
        usort($atributos_nuevos, "comparator");
        usort($atributos_perdidos, "comparator");

        function addToResult($name,$value,$result) {
            $result[$name][] = $value;
            return $result; 
        };
        function diff($a,$b) {
            $ha = count($a) ? $a[0] : NULL;
            $hb = count($b) ? $b[0] : NULL;
            if(!$ha && !$hb) return array("a"=>array(),"b"=>array());
            if(!$ha) return addToResult("b", $hb, diff($a, array_slice($b,1)));
            elseif(!$hb) return addToResult("a", $ha, diff(array_slice($a,1), $b));
            $c = comparator($ha,$hb);
            return $c == 0
                ? $diff($a,$b)
                : ($c < 0
                    ? addToResult("a", $ha, diff(array_slice($a,1), $b))
                    : addToResult("b", $hb, diff($a, array_slice($b,1))));
        };
        
        $r = diff($atributos_nuevos, $atributos_perdidos);
        return array("atributos_nuevos"=>$r['a'],"atributos_perdidos"=>$r['b']);
    }

    public function actualizar_atributos($familia, $oldmadre, $data)
    {
        $this->clean_cache($familia->codfamilia);

        $articulo = new \articulo();
        $articulos = $articulo->get_referencia_de_articulos_de_familia($familia->codfamilia);
        // Si no hay articulos no hay nada que actualizar
        if(!count($articulos)) {
            return;
        }

        $diff = $this->diff_atributos_cambio_familia($familia->madre, $oldmadre);
        $atributos_perdidos = $diff["atributos_perdidos"];
        $atributos_nuevos = $diff["atributos_nuevos"];

        foreach($atributos_perdidos as $viejo) {
            $data = new \atributo_articulo_familia();
            $data->delete_atributo_from_articulos($viejo->idatributofamilia, $articulos);
        }
 
        foreach($atributos_nuevos as $nuevo) {
            if($nuevo->permitirnulo && !isset($_POST['atrfam_'.$nuevo->idatributofamilia]) && !$_POST['atrfam_'.$nuevo->idatributofamilia]) {
                continue;
            }
            $valor = $_POST['atrfam_'.$nuevo->idatributofamilia];
            if(strlen($valor)) {
                $nuevo->set_atributo_articulos($valor, $familia->codfamilia);
            }
        }
    }

    public function set_atributo_articulos($valor, $familia = NULL, $only_if_not_exist = FALSE)
    {
        $this->clean_cache();

        // Si articulo es null, cambiar todos los articulos de la familia
        $articulo = new \articulo();
        if(!$familia) {
            $familia = $this->codfamilia;
        }
        $referencias = $articulo->get_referencia_de_articulos_de_familia($familia);

        foreach($referencias as $referencia) {
            $data = new \atributo_articulo_familia();
            $data->referencia = $referencia;
            $data->idatributofamilia = $this->idatributofamilia;
            if($only_if_not_exist && $data->exists()) {
                continue;
            }
            $data->valor = $valor;
            $data->save();
        }
    }

    // Devuelve un array con los atributos clasificados por familia
    public function all()
    {
        $familias_con_atributos = $this->cache->get_array('m_atributos_familia_all');
        if(!$familias_con_atributos) {
            $familias_con_atributos = array();
            $data = $this->db->select("SELECT codfamilia,atributosfamilia.idatributofamilia,atributosfamilia.nombre,tipo,permitirnulo,GROUP_CONCAT(valoresatributosfamilia.idvaloratributo) AS valores_id,GROUP_CONCAT(valoresatributosfamilia.nombre) AS valores_nombre FROM atributosfamilia LEFT JOIN valoresatributosfamilia ON atributosfamilia.idatributofamilia = valoresatributosfamilia.idatributofamilia GROUP BY atributosfamilia.idatributofamilia;");
            if ($data) {
                foreach ($data as $d) {
                    if(array_key_exists($d['codfamilia'], $familias_con_atributos)) {
                        array_push($familias_con_atributos[$d['codfamilia']], $d);
                    }
                    else {
                        $familias_con_atributos[$d['codfamilia']] = array($d);
                    }
                }

                $familia = new \familia();
                $familias = $familia->all();
                // Asignamos los atributos a cada familia
                foreach($familias as &$fam) {
                    if(array_key_exists($fam->codfamilia, $familias_con_atributos)) {
                        $fam->atributos = $familias_con_atributos[$fam->codfamilia];
                    }
                    else {
                        $fam->atributos = array();
                    }
                }
                // Separar las familias con madre y las sin madres
                list($madres,$hijas) = array_reduce($familias, function($acc,&$it) {
                    if(is_null($it->madre)) {
                        $acc[0][] = $it; 
                    }
                    else {
                        $acc[1][] = $it;
                    }
                    return $acc;
                }, array(array(),array()));

                // Mientras hayan hijas sin haber copiado los atributos de su madre (cuando ella
                // haya copiado los suyos) buscar las hijas que tienen una madre lista, copiar 
                // atributos y ponerlas como las madres de la siguiente generación
                while($hijas) {
                    list($madres,$hijas) = array_reduce($hijas, function($acc,&$it) use($madres) {
                        // Comprobar si su madre esta en madres, en cuyo caso copiar atributos
                        $madre = array_find($madres,function($v) use($it) {
                            return $it->madre == $v->codfamilia;
                        });
                        if($madre) {
                            $it->atributos = array_merge($it->atributos, $madre->atributos);
                            $acc[0][] = $it;
                        }
                        else {
                            $acc[1][] = $it;
                        }
                        return $acc;
                    }, array(array(),array()));
                }

                $familias_con_atributos = array_reduce($familias, function($acc,&$it) {
                    if($it->atributos) {
                        $acc[$it->codfamilia] = array_map(function($v) {
                            $v['permitirnulo'] = $v['permitirnulo'] == '1';
                            unset($v["codfamilia"]);
                            if($v["valores_nombre"]) {
                                $v["valores"] = array_combine(explode(",",$v["valores_id"]), explode(",",$v["valores_nombre"]));
                            }
                            unset($v["valores_nombre"]);
                            unset($v["valores_id"]);
                            return $v;
                        }, $it->atributos);
                    }
                    return $acc;
                },array());

                $this->cache->set('m_atributos_familia_all', $familias_con_atributos);
            }
        }

        return $familias_con_atributos;
    }

    public function count_from_familia($codfamilia)
    {
        $familia = new \familia();
        $madres = $familia->get_lista_madres($codfamilia);
        $madres[] = $codfamilia;
        $buscar_en_madres = implode(", ", array_map(function($v) {
            return $this->var2str($v);
        }, $madres));
        $data = $this->db->select("SELECT idatributofamilia FROM " . $this->table_name . " WHERE codfamilia IN (" . $buscar_en_madres . ")");
        if($data) {
            return count($data);
        }
        return FALSE;
    }

    public function get_atributos_de_familia($codfamilia, $add_valores = FALSE)
    {
        $atrlist = $this->cache->get_array('m_atributos_familia_'.$codfamilia);
        if (!$atrlist) {
            /// si la lista no está en caché, leemos de la base de datos
            $familia = new \familia();
            $madres = $familia->get_lista_madres($codfamilia);
            $madres[] = $codfamilia;
            $buscar_en_madres = implode(", ", array_map(function($v) {
                return $this->var2str($v);
            }, $madres));
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codfamilia IN (" . $buscar_en_madres . ") ORDER BY codfamilia != ". $this->var2str($codfamilia) .",codfamilia,nombre ASC;");
            if ($data) {
                foreach ($data as $d) {
                    $atributo = new \atributo_familia($d);
                    // Añadir el nombre de la familia al atributo
                    $atributo->familia = $familia->get($atributo->codfamilia)->descripcion;
                    $atrlist[] = $atributo;
                }
            }

            /// guardamos la lista en caché
            $this->cache->set('m_atributos_familia_'.$codfamilia, $atrlist);
        }

        // Añadir valores
        if($add_valores) {
            foreach($atrlist as &$atr) {
                if($atr->tipo == 2) {
                    $valores = new \valor_atributo_familia();
                    $atr->valores = $valores->get_valores_de_atributo($atr->idatributofamilia);
                }
            }
        }

        return $atrlist;
    }

    public function valores_activos($codfamilia, $criteria_atributos, $filtrado_sql)
    {
        $atributo = new \atributo_familia();
        $lista_atributos = array_map(function($v) {
            return $v->idatributofamilia;
        }, array_filter($atributo->get_atributos_de_familia($codfamilia), function($v) use($criteria_atributos) {
            return !isset($criteria_atributos[$v->idatributofamilia]) || !$criteria_atributos[$v->idatributofamilia];
        }));
        $sql = "SELECT a.referencia " . $filtrado_sql;
        $filtered_data = $this->db->select($sql);

        if(!$filtered_data || !count($filtered_data)) {
            return FALSE;
        }

        $total = count($filtered_data);
        $filtered_data = implode(",",array_map(function($v) {
            return $this->var2str($v["referencia"]);
        }, $filtered_data));

        $sql = "SELECT valor,idatributofamilia,COUNT(*) AS no_nulos FROM atributosarticulosfamilia WHERE referencia IN (" . $filtered_data . ") AND idatributofamilia IN (" . implode(",", $lista_atributos) . ") GROUP BY valor,idatributofamilia HAVING COUNT(*) > 0;";

        $data = $this->db->select($sql);

        $result = array();
        if($data) {
            $result = array_fill_keys($lista_atributos, array("valores"=>array(),"usados"=>0));

            foreach($data as $d) {
                $result[$d["idatributofamilia"]]["valores"][] = $d["valor"];
                $result[$d["idatributofamilia"]]["usados"] += $d["no_nulos"];
            }
            foreach($result as &$atributo) {
                if($total - $atributo["usados"] > 0) {
                    $atributo["valores"][] = "-1";
                }
                unset($atributo["usados"]);
            }
        }

        return $result;
    }

}
