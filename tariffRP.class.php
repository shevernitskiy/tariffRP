<?php

/**
 * Класс для рассчета стоимости доставки с помощью сервиса tariff.russianpost.ru
 * 
 * Данный выполняет функции рассчета стоимости доставки обращаясь к сервису tariff.russianpost.ru
 * Для корректной работы классу необходим файл country.json, который содержит данные
 * для международных отправлений посредством Почты России и EMS.
 * Наличие этого файла не является строгим условием, но без него функционал класса буде
 * суещственно ограничен.
 * 
 * Типичное использование:
 * 
 * $t = new tariffRP();
 * $t   ->setIsAvia(true)
 *      ->setWight(1500)                    //  Вес задается в граммах
 *      ->setDestination('США')             //  Пункт назначение на русском, английском, или цифровым или буквенным кодом почты
 *      ->setDeclareValue(15500)            //  Сумма в рублях
 *      ->setType('pakageDeclareValue');    //  Типы отправлений: EMS, pakageDeclareValue
 * $t->getCost();                           //  getCost(false) - если без НДС
 * $t->getMinDays();                        //  Только для EMS
 * $t->getMaxDays();                        //  Только для EMS
 * $t->getEmsZone();                        //  Только для EMS
 *                                          
 * @uses country.json - файл с данными по кодам стран и зонам доставки
 * @throws Exception
 * 
 */

class tariffRP
{

    protected $url = 'http://tariff.russianpost.ru/tariff/v1/calculate?json';

    protected $isAvia, $weight, $destination, $declareValue, $type;
    protected $cn;

    public function __construct()
    {
        $this->date = date('Ymd');
        if (file_exists(dirname(__FILE__).'\country.json')) {
            $this->cn = json_decode(file_get_contents(dirname(__FILE__).'\country.json'), true);
        }
    }

    /**
     * Универсальный сеттер
     *
     * @param string    $property
     * @param mixed     $value
     * @return mixed    $this
     */
    public function set($property, $value)
    {
        $this->$property = $value;
        return $this;
    }

    /**
     * Универсальный геттер
     *
     * @param string    $property
     * @return mixed    $this
     * @throws Exception
     */
    public function get($property)
    {
        if (isset($this->$property)) {
            return $this->$property;
        } else {
            throw new Exception(__METHOD__.' -> Переменная не существует');
        }
    }

    /**
     * Сеттеры для отдельных переменных запроса
     * В них организована проверка в соотвествии с типом переменной
     *
     * @param mixed $value
     * @return mixed $this
     */
    public function setIsAvia($value)
    {
        if (is_numeric($value)) {
            $this->isAvia = $value;
        } elseif (is_bool($value) && $value == true) {
            $this->isAvia = 2;
        } else {
            throw new Exception(__METHOD__.' -> Значение должно быть true/false или 0/1/2.');
        }
        return $this;
    }

    public function setWeight($value)
    {
        if (is_numeric($value)) {
            $this->weight = $value;
        } else {
            throw new Exception(__METHOD__.' -> Значение должно быть числом (вес в граммах).');
        }
        return $this;
    }

    public function setDestination($value)
    {
        if (is_numeric($value)) {
            $this->destination = $value;
        } elseif (!$this->cn) {
            throw new Exception(__METHOD__.' -> Отсутсвует файл country.json. Допускается использовать только числовые коды стран');
        } else {
            if (preg_match("/[а-яё]/iu", $value)) {
                $names = array_column($this->cn, 'ruName');
                $key = array_search($value, $names);
                if ($key !== false) {
                    $this->destination = $this->cn[$key]['codeNum'];
                    return $this;
                } else {
                    throw new Exception(__METHOD__.' -> В списка стран страны с названием '.$value.' не найдено');
                }
            } else {
                $key = false;
                $names = array_column($this->cn, 'enName');
                $key = array_search($value, $names);
                if ($key !== false) {
                    if (array_key_exists('codeNum',$this->cn[$key])) {
                        $this->destination = $this->cn[$key]['codeNum'];
                        return $this;
                    } else {
                        throw new Exception(__METHOD__.' -> Такая страна может и есть, но почта РФ о ней не ведает');
                    }
                }
                if (strlen($value) == 3) {
                    $key = false;
                    $names = array_column($this->cn, 'code3');
                    $key = array_search($value, $names);
                    if ($key !== false) {
                        if (array_key_exists('codeNum',$this->cn[$key])) {
                            $this->destination = $this->cn[$key]['codeNum'];
                            return $this;
                        } else {
                            throw new Exception(__METHOD__.' -> Такая страна может и есть, но почта РФ о ней не ведает');
                        }
                    }
                } elseif (strlen($value) == 2) {
                    $key = false;
                    $names = array_column($this->cn, 'code2');
                    $key = array_search($value, $names);
                    if ($key !== false) {
                        if (array_key_exists('codeNum',$this->cn[$key])) {
                            $this->destination = $this->cn[$key]['codeNum'];
                            return $this;
                        } else {
                            throw new Exception(__METHOD__.' -> Такая страна может и есть, но почта РФ о ней не ведает');
                        }
                    }
                }
                throw new Exception(__METHOD__.' -> В списке стран страны с названием '.$value.' не найдено');                                  
            }
        }
        return $this;
    }

    public function setDeclareValue($value)
    {
        if (is_numeric($value)) {
            $this->declareValue = $value*100;
        } else {
            throw new Exception(__METHOD__.' -> Значение должно быть суммой в рублях');
        }
        return $this;
    }

    public function setUrl($value)
    {
        $this->url = $value;
        return $this;
    }
    
    public function setType($value)
    {
        switch ($value) {
            case 4021:
                $this->type = 4021;
                break;
            case 'pakageDeclareValue':
                $this->type = 4021;
                break;
            case 7031:
                $this->type = 7031;
                break;
            case 'EMS':
                $this->type = 7031;
                break;
            default:
                throw new Exception(__METHOD__.' -> Тип отправления не поддерживается');
                break;
        }
        return $this;
    }

    /**
     * Функция генерации ссылки для выполнения запроса к сервису
     * Все необходимые для запроса перменные должны быть заданы заранее
     *
     * @return string
     */
    public function makeUrl()
    {
        $str = $this->url;
        if ($this->type) {
            $str .= '&object='.$this->type;
            switch ($this->type) {
                case 4021:
                    if ($this->isAvia) {
                        $str .= '&isavia=2';
                    }
                    if ($this->declareValue) {
                        $str .= '&sumoc='.$this->declareValue;
                    }
                    break;
                case 7031:
                    $str .= '&service=10';
                    break;
                default:
                    throw new Exception(__METHOD__.' -> Тип отправления не поддерживается. Воспользуйтесь tariffRP::setType($value)');
                    break;
            }
        } else {
            throw new Exception(__METHOD__.' -> Тип отправления не задан. Воспользуйтесь tariffRP::setType($value)');
        }
        if ($this->weight) {
            $str .= '&weight='.$this->weight;
        } else {
            throw new Exception(__METHOD__.' -> Вес не задан, воспользуйтесь tariffRP::setWeight($value)');
        }
        if ($this->destination) {
            $str .= '&country='.$this->destination;
        } else {
            throw new Exception(__METHOD__.' -> Код пункта назначения не задан, вопспользуйтесь tariffRP::setDestination($value)');
        }
        if ($this->date) {
            $str .= '&date='.$this->date;
        }
        return $str;
    }

    /**
     * Функция обращения к сервису посредством GET-запроса
     *
     * @uses tariffRP::makeUrl()
     * @return array
     */
    public function doRequest()
    {
        $array = json_decode(file_get_contents($this->makeUrl()), true);
        if (array_key_exists('error', $array)) {
            throw new Exception(__METHOD__.' -> Ошибка в запросе: '.$array['error'][0]);
        }
        return $array;
    }

    /**
     * Функция получения суммы доставки из массива с ответными данными
     *
     * @uses tariffRP::doRequest()
     * @param bool $nds - если $true - показывает стоимость с НДС, если flase - без
     * @return string
     */
    public function getCost($nds = true)
    {
        if ($nds) {
            return $this->doRequest()['paynds']/100;
        } else {
            return $this->doRequest()['pay']/100;
        }
    }

    /**
     * Функция запроса тарифной зоны EMS
     * 
     * @uses country.json
     * @uses tariffRP::searchWithParam()
     * @return int 
     */
    public function getEmsZone()
    {
        if (!$this->destination) {
            throw new Exception(__METHOD__.' -> Код пункта назначения не задан, вопспользуйтесь tariffRP::setDestination($value)');
        } elseif (!$this->cn) {
            throw new Exception(__METHOD__.' -> Отсутсвует файл country.json');
        } else {
            $zone = $this->searchWithParam('codeNum', $this->destination, 'emsZone');
            if ($zone !== false) {
                return $zone;
            } else {
                throw new Exception(__METHOD__.' -> EMS зона не известна');
            }
        }
        return false;
    }

    /**
     * Функция запроса минимального срока доставки EMS
     * 
     * @uses country.json
     * @uses tariffRP::searchWithParam()
     * @return int 
     */    
    public function getMinDays()
    {
        if (!$this->destination) {
            throw new Exception(__METHOD__.' -> Код пункта назначения не задан, вопспользуйтесь tariffRP::setDestination($value)');
        } elseif (!$this->cn) {
            throw new Exception(__METHOD__.' -> Отсутсвует файл country.json');
        } else {
            $days = $this->searchWithParam('codeNum', $this->destination, 'minDays');
            if ($days !== false) {
                return $days;
            } else {
                throw new Exception(__METHOD__.' -> Сроки неизвестны');
            }
        }
        return false;
    }

    /**
     * Функция запроса максимального срока доставки EMS
     * 
     * @uses country.json
     * @uses tariffRP::searchWithParam()
     * @return int 
     */     
    public function getMaxDays()
    {
        if (!$this->destination) {
            throw new Exception(__METHOD__.' -> Код пункта назначения не задан, вопспользуйтесь tariffRP::setDestination($value)');
        } elseif (!$this->cn) {
            throw new Exception(__METHOD__.' -> Отсутсвует файл country.json');
        } else {
            $days = $this->searchWithParam('codeNum', $this->destination, 'maxDays');
            if ($days !== false) {
                return $days;
            } else {
                throw new Exception(__METHOD__.' -> Сроки неизвестны');
            }
        }
        return false;
    }

    /**
     * Вспомогательная функция поиска значения поля
     * 
     * Возвращает значение поля $targetField страны, у которой $fieldName = $fieldValue
     * Работает только при наличии файла country.json_decode
     * 
     * @uses country.json
     * @param string $fieldName 
     * @param mixed $fieldValue 
     * @param string $targetField 
     * @return mixed 
     */
    private function searchWithParam($fieldName, $fieldValue, $targetField)
    {
        if ($this->cn) {
            foreach ($this->cn as $key => $row) {
                if (array_key_exists($fieldName, $row)) {
                    if ($row[$fieldName] == $fieldValue) {
                        if (array_key_exists($targetField, $row)) {
                            return $row[$targetField];
                        } else {
                            return false;
                        }
                    }
                }
            }
        }
        return false;
    }
}
