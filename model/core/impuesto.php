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

namespace FacturaScripts\model;

/**
 * Un impuesto (IVA) que puede estar asociado a artículos, líneas de albaranes,
 * facturas, etc.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class impuesto extends \fs_model {

    /**
     * Clave primaria. varchar(10).
     * @var string
     */
    public $codimpuesto;

    /**
     * Código de la subcuenta para ventas.
     * @var string
     */
    public $codsubcuentarep;

    /**
     * Código de la subcuenta para compras.
     * @var string
     */
    public $codsubcuentasop;
    public $descripcion;
    public $iva;
    public $recargo;

    public function __construct($i = FALSE) {
        parent::__construct('impuestos');
        if ($i) {
            $this->codimpuesto = $i['codimpuesto'];
            $this->codsubcuentarep = $i['codsubcuentarep'];
            $this->codsubcuentasop = $i['codsubcuentasop'];
            $this->descripcion = $i['descripcion'];
            $this->iva = floatval($i['iva']);
            $this->recargo = floatval($i['recargo']);
        } else {
            $this->codimpuesto = NULL;
            $this->codsubcuentarep = NULL;
            $this->codsubcuentasop = NULL;
            $this->descripcion = NULL;
            $this->iva = 0.0;
            $this->recargo = 0.0;
        }
    }

    protected function install() {
        $this->clean_cache();
        return "INSERT INTO " . $this->table_name . " (codimpuesto,descripcion,iva,recargo) VALUES "
                . "('IVA0','IVA 0%','0','0'),('IVA21','IVA 21%','21','5.2'),"
                . "('IVA10','IVA 10%','10','1.4'),('IVA4','IVA 4%','4','0.5');";
    }

    public function url() {
        if (is_null($this->codimpuesto)) {
            return 'index.php?page=contabilidad_impuestos';
        }

        return 'index.php?page=contabilidad_impuestos#' . $this->codimpuesto;
    }

    /**
     * Devuelve TRUE si el impuesto es el predeterminado del usuario
     * @return type
     */
    public function is_default() {
        return ( $this->codimpuesto == $this->default_items->codimpuesto() );
    }

    /**
     * Devuelve el impuesto con código $cod
     * @param type $cod
     * @return boolean|\impuesto
     */
    public function get($cod) {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codimpuesto = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \impuesto($data[0]);
        }

        return FALSE;
    }

    public function get_by_iva($iva) {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE iva = " . $this->var2str(floatval($iva)) . ";");
        if ($data) {
            return new \impuesto($data[0]);
        }

        return FALSE;
    }

    public function exists() {
        if (is_null($this->codimpuesto)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name
                        . " WHERE codimpuesto = " . $this->var2str($this->codimpuesto) . ";");
    }

    public function test() {
        $status = FALSE;

        $this->codimpuesto = trim($this->codimpuesto);
        $this->descripcion = $this->no_html($this->descripcion);

        if (strlen($this->codimpuesto) < 1 OR strlen($this->codimpuesto) > 10) {
            $this->new_error_msg("Código del impuesto no válido. Debe tener entre 1 y 10 caracteres.");
        } else if (strlen($this->descripcion) < 1 OR strlen($this->descripcion) > 50) {
            $this->new_error_msg("Descripción del impuesto no válida.");
        } else {
            $status = TRUE;
        }

        return $status;
    }

    public function save() {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET codsubcuentarep = " . $this->var2str($this->codsubcuentarep)
                        . ", codsubcuentasop = " . $this->var2str($this->codsubcuentasop)
                        . ", descripcion = " . $this->var2str($this->descripcion)
                        . ", iva = " . $this->var2str($this->iva)
                        . ", recargo = " . $this->var2str($this->recargo)
                        . "  WHERE codimpuesto = " . $this->var2str($this->codimpuesto) . ";";
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codimpuesto,codsubcuentarep,codsubcuentasop,
                     descripcion,iva,recargo) VALUES (" . $this->var2str($this->codimpuesto)
                        . "," . $this->var2str($this->codsubcuentarep)
                        . "," . $this->var2str($this->codsubcuentasop)
                        . "," . $this->var2str($this->descripcion)
                        . "," . $this->var2str($this->iva)
                        . "," . $this->var2str($this->recargo) . ");";
            }

            return $this->db->exec($sql);
        }
        
        return FALSE;
    }

    public function delete() {
        $this->clean_cache();
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE codimpuesto = " . $this->var2str($this->codimpuesto) . ";");
    }

    private function clean_cache() {
        $this->cache->delete('m_impuesto_all');
    }

    /**
     * Devuelve un array con todos los impuestos
     * @return \impuesto
     */
    public function all() {
        /// leemos la lista de la caché
        $impuestolist = $this->cache->get_array('m_impuesto_all');
        if (!$impuestolist) {
            /// si no encontramos la lista en caché, leemos de la base de datos
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY iva DESC;");
            if ($data) {
                foreach ($data as $i) {
                    $impuestolist[] = new \impuesto($i);
                }
            }

            /// guardamos la lista en caché
            $this->cache->set('m_impuesto_all', $impuestolist);
        }

        return $impuestolist;
    }

}
