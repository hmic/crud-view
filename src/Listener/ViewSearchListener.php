<?php
namespace CrudView\Listener;

use Cake\Event\Event;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use Crud\Listener\BaseListener;

class ViewSearchListener extends BaseListener
{

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'enabled' => null,
        'autocomplete' => true,
        'selectize' => true,
    ];

    /**
     * implementedEvents
     *
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Crud.afterPaginate' => ['callable' => 'afterPaginate']
        ];
    }

    /**
     * afterPaginate
     *
     * Only after a crud paginate call does this listener do anything. So listen
     * for that
     *
     * @param \Cake\Event\Event $event Event.
     * @return void
     */
    public function afterPaginate(Event $event)
    {
        $event;
        if (!$this->_table()->behaviors()->has('Search')) {
            return;
        }

        $enabled = $this->getConfig('enabled') ?: !$this->_request()->is('api');
        if (!$enabled) {
            return;
        }

        $fields = $this->fields();
        $this->_controller()->set('searchInputs', $fields);
    }

    /**
     * [fields description]
     *
     * @return array
     */
    public function fields()
    {
        return $this->getConfig('fields') ?: $this->_deriveFields();
    }

    /**
     * [_deriveFields description]
     *
     * @return array
     */
    protected function _deriveFields()
    {
        $table = $this->_table();
        $request = $this->_request();

        if (method_exists($table, 'searchConfiguration')) {
            $filters = $table->searchConfiguration();
        } else {
            $filters = $table->searchManager();
        }

        $fields = [];
        $schema = $table->getSchema();
        $config = $this->_config;

        foreach ($filters->all() as $filter) {
            if ($filter->getConfig('form') === false) {
                continue;
            }

            $field = $filter->name();
            $input = [];

            $filterFormConfig = $filter->config();
            if (!empty($filterFormConfig['form'])) {
                $input = $filterFormConfig['form'];
            }

            $input += [
                'label' => Inflector::humanize(preg_replace('/_id$/', '', $field)),
                'required' => false,
                'type' => 'text'
            ];

            $input['value'] = $request->getQuery($field);

            if (empty($input['options']) && $schema->columnType($field) === 'boolean') {
                $input['options'] = ['No', 'Yes'];
                $input['type'] = 'select';
            }

            if (!empty($input['options'])) {
                $input['empty'] = true;
                if (empty($input['class']) && !$config['selectize']) {
                    $input['class'] = 'no-selectize';
                }

                $fields[$field] = $input;

                continue;
            }

            if (empty($input['class']) && $config['autocomplete']) {
                $input['class'] = 'autocomplete';
            }

            $urlArgs = [];

            $fieldKeys = isset($input['fields']) ? $input['fields'] : ['id' => $field, 'value' => $field];
            if (is_array($fieldKeys)) {
                foreach ($fieldKeys as $key => $val) {
                    $urlArgs[$key] = $val;
                }
            }

            unset($input['fields']);
            $url = array_merge(['action' => 'lookup', '_ext' => 'json'], $urlArgs);
            $input['data-url'] = Router::url($url);

            $fields[$field] = $input;
        }

        return $fields;
    }
}
