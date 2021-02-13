<?php
namespace com\github\tncrazvan\catpaw\qb\traits;

use com\github\tncrazvan\catpaw\attributes\helpers\Factory;
use com\github\tncrazvan\catpaw\qb\tools\Binding;
use com\github\tncrazvan\catpaw\qb\tools\Column;
use com\github\tncrazvan\catpaw\qb\tools\Entity;
use com\github\tncrazvan\catpaw\qb\tools\QueryBuilder;
use com\github\tncrazvan\catpaw\qb\tools\interfaces\IntoCallback;
use com\github\tncrazvan\catpaw\qb\tools\QueryConst;

trait Insert{
    private ?IntoCallback $default_into_callback = null;
    /**
     * Insert data.
     * @param mixed $repository an object of a class that extends Repository, this object will be inserted to the database.
     * @param callable $callback a callback function which if specified will be passed a clone of the original $repository.
     * This cloned object can be inserted to the database instead of the $repository object.
     * This can be useful if you want to make small changes to the object before inserting it to 
     * the database but don't want to change the actual $repository.
     * This $callback should return an object of a class that extends Repository, or an array of them, 
     * in which case the whole array will be inserted to the database.
     * @return QueryBuilder the QueryBuilder
     */
    public function insert(string $classname, Entity $entity, IntoCallback $callback=null):QueryBuilder{
        $this->reset();
        $this->current_classname = $classname;
        Entity::_sync_entity_columns_from_props($entity);
        $this->add(QueryConst::INSERT);
        $cloning = true;
        if($callback === null){
            $cloning = false;
            if($this->default_into_callback === null)
                $this->default_into_callback = new class implements IntoCallback{
                    public function run(?Entity $entity){
                        return $entity;
                    }
                };
            $callback = $this->default_into_callback;
        }
        $this->into($classname,$entity,$callback,$cloning);
        return $this;
    }

    /**
     * Specify to which table the data should be inserted to
     * @return QueryBuilder the QueryBuilder
     */
    private function into(string &$classname, Entity $entity, IntoCallback &$callback, bool $cloning=true):QueryBuilder{
        $entity_r = Factory::make($classname);
        $this->add(QueryConst::INTO);
        $this->add($entity_r->tableName());
        $this->mapColumns($entity_r->getEntityColumns());
        if($cloning){
            $clone = clone($entity);
            $results = $callback->run($clone);
        }else{
            $results = $callback->run($entity);
        }
        if(\is_array($results)){
            $this->add(QueryConst::VALUES);
            $length = \count($results);
            for($i=0;$i<$length;$i++){
                if($i>0) 
                    $this->add(QueryConst::COMMA);
                $this->value($results[$i]);
            }
        }else{
            $this->add(QueryConst::VALUE);
            $this->value($results);
        }
        return $this;
    }

    /**
     * Map the column names into the query string
     * @return QueryBuilder the QueryBuilder
     */
    private function mapColumns(array $columns=[]){
        $length = count($columns);
        if($length <= 0) return $this;
        $first = true;
        $this->add(QueryConst::PARENTHESIS_LEFT);
        foreach($columns as $name => &$object){
            if($first) $first = false;
            else $this->add(QueryConst::COMMA);
            $this->add($name);
        }
        $this->add(QueryConst::PARENTHESIS_RIGHT);
        return $this;
    }

    /**
     * Add the data of $repository into the query string and into the bindings array so 
     * that the values can be bound to the statement later (before the statement execute)
     * @return QueryBuilder the QueryBuilder
     */
    private function value(Entity &$entity):QueryBuilder{
        $columns = &$entity->getEntityColumns();
        $random = '';
        $vname = '';
        $this->add(QueryConst::PARENTHESIS_LEFT);
        $first = true;
        foreach($columns as $name => &$object){
            if($first) $first = false;
            else $this->add(QueryConst::COMMA);
            $random = \uniqid();
            $this->add(QueryConst::VARIABLE_SYMBOL.$name.$random);
            $type = $columns[$name]->getColumnType();
            switch($type){
                case Column::PARAM_FLOAT:
                case Column::PARAM_DOUBLE:
                case Column::PARAM_DECIMAL:
                    $type = \PDO::PARAM_STR;
                break;
            }
            $this->bind($name.$random,new Binding($columns[$name]->getColumnValue(),$type));
        }
        $this->add(QueryConst::PARENTHESIS_RIGHT);
        return $this;
    }
    
}