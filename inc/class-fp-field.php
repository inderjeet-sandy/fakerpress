<?php
namespace FakerPress;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ){
	die;
}

class Field {

	const plugin = 'fakerpress';
	const abbr = 'fp';

	public static function abbr( $str = '' ){
		return self::abbr . '-' . $str;
	}

	public $type = 'raw';

	public $id;

	public $field;

	public $container;

	public $has_container = true;

	public $has_wrap = true;

	public static $default_container = array(
		'label' => '',
		'description' => '',
		'attributes' => array(),
		'actions' => array(),
		'heads' => array(),
		'class' => array(),
		'wrap' => array(
			'class' => array()
		),
		'blocks' => array( 'label', 'fields', 'description', 'actions' ),
	);

	public static $valid_types = array(
		'heading',
		'input',
		'text',
		'dropdown',
		'range',
		'interval',
		'number',
		'hidden',
		'meta',
		// 'textarea',
		// 'wysiwyg',
		'radio',
		'checkbox',
		'raw',
	);

	public function __construct( $type, $field, $container = array() ) {
		// Default Error Structure
		$this->error = false;

		// Non Valid types are just set to Raw
		if ( ! self::is_valid_type( $type ) ){
			$type = 'raw';
		}

		if ( is_string( $field ) ){
			$this->field = (object) array(
				'id' => $field,
			);
		} else {
			// Setup the Container if required
			$this->field = (object) $field;
		}

		$container = (object) wp_parse_args( $container, self::$default_container );

		// set the ID
		$this->type = $type;
		if ( ! isset( $this->field->id ) ){
			$this->id = (array) self::abbr( uniqid() );
		} else {
			$this->id = (array) $this->field->id;
		}

		$this->callback = null;
		$this->conditional = true;

		$this->label = $container->label;
		$this->description = $container->description;
		$this->actions = $container->actions;
		$this->blocks = $container->blocks;
		$this->class = $container->class;
		$this->wrap = $container->wrap;
	}

	public function output( $print = false ) {
		if ( ! $this->conditional ) {
			return false;
		}

		if ( $this->callback && is_callable( $this->callback ) ) {
			// if there's a callback, run it
			call_user_func( $this->callback );
		} elseif ( in_array( $this->type, self::$valid_types ) ) {
			// the specified type exists, run the appropriate method
			$field = call_user_func_array( array( __CLASS__, 'type_' . $this->type ), array( $this->field, $this, 'string', array() ) );

			// filter the output
			$field = apply_filters( self::plugin . '/fields/field-output-' . $this->type, $field, $this );

			if ( $print ){
				echo balanceTags( $field );
			} else {
				return $field;
			}
		} else {
			return false;
		}
	}

	public function build( $content, $output = 'string', $html = array() ) {
		$content = (array) $content;
		$key = array_search( 'fields', $this->blocks );

		$before = array_filter( array_slice( $this->blocks, 0, $key ), 'is_array' );
		$before_content = array();
		foreach ( $before as $i => $block ) {
			$_html = '';
			if ( ! empty( $block['html'] ) ){
				$_html = $block['html'];
				unset( $block['html'] );
			}
			$before_content[] = '<td' . self::attr( $block ) . '>' . $_html . '</td>';
		}

		$after = array_filter( array_slice( $this->blocks, $key + 1, count( $this->blocks ) - ( $key + 1 ) ), 'is_array' );
		$after_content = array();
		foreach ( $after as $i => $block ) {
			$_html = '';
			if ( ! empty( $block['html'] ) ){
				$_html = $block['html'];
				unset( $block['html'] );
			}
			$after_content[] = '<td' . self::attr( $block ) . '>' . $_html . '</td>';
		}

		if ( in_array( 'table', $this->blocks ) ){
			$html[] = self::start_table( $this );
		}

		$html[] = self::start_container( $this );
		$html[] = implode( "\r\n", $before_content );

		if ( in_array( 'label', $this->blocks ) ){
			$html[] = self::label( $this );
		}

		$html[] = self::start_wrap( $this );
		$html[] = implode( "\r\n", $content );
		$html[] = self::end_wrap( $this );

		$html[] = implode( "\r\n", $after_content );

		$html[] = self::end_container( $this );

		if ( in_array( 'table', $this->blocks ) ){
			$html[] = self::end_table( $this );
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function start_table( $container, $output = 'string', $html = array() ) {
		$html[] = self::type_heading( array(
			'type' => 'heading',
			'title' => $container->label,
			'description' => $container->description,
		), null, 'string' );

		$html[] = '<table class="' . self::abbr( 'table-' . implode( '-', $container->id ) ) . '">';
		if ( ! empty( $container->heads ) ){
			$html[] = '<thead>';
			foreach ( $container->heads as $head ) {
				$_html = '';
				if ( ! empty( $head['html'] ) ){
					$_html = $head['html'];
					unset( $head['html'] );
				}
				$html[] = '<th' . self::attr( $head ) . '>' . $_html . '</th>';
			}
			$html[] = '</thead>';
		}
		$html[] = '<tbody>';

		$html = apply_filters( self::plugin . '/fields/field-start_table', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function end_table( $container, $output = 'string', $html = array() ) {
		$html[] = '</tbody>';
		$html[] = '</table>';

		$html = apply_filters( self::plugin . '/fields/field-end_table', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function start_container( $container, $output = 'string', $html = array() ) {
		if ( ! is_array( $container->class ) ){
			$container->class = (array) $container->class;
		}
		$container->class[] = 'field-container';
		$container->class[] = 'type-' . $container->type . '-container';

		if ( is_wp_error( $container->error ) ){
			$container->class[] = 'error';
		}

		$container->class = array_map( array( __CLASS__, 'abbr' ) , $container->class );

		if ( ! in_array( 'table' , $container->blocks ) ){
			$html[] = '<tr id="' . self::id( $container->id, true ) . '" class="' . implode( ' ', $container->class ) . '">';
		}

		$html = apply_filters( self::plugin . '/fields/field-start_container', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function end_container( $container, $output = 'string', $html = array() ) {
		if ( ! in_array( 'table' , $container->blocks ) ){
			$html[] = '</tr>';
		}

		$html = apply_filters( self::plugin . '/fields/field-end_container', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function wrapper( $content = array(), $field = array(), $output = 'string' ){
		$attributes = (object) array();
		$attributes->class[] = 'field-wrap';
		$attributes->class[] = 'type-' . $field->type . '-wrap';

		$html = array();
		if ( ! empty( $content ) ){
			$html[] = '<fieldset' . self::attr( $attributes ) . '>';
			$html[] = implode( "\r\n", (array) $content );
			if ( ! empty( $field->label ) ){
				$html[] = '<label class="' . self::abbr( 'internal-label' ) . '">' . $field->label . '</label>';
			}
			$html[] = '</fieldset>';
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function start_wrap( $container, $output = 'string', $html = array() ) {
		$container->wrap['class'][] = 'field-wrap';
		$container->wrap['class'][] = 'type-' . $container->type . '-wrap';
		if ( in_array( 'fields', $container->blocks ) ){
			$html[] = '<td colspan="1">';
			$html[] = '<fieldset' . self::attr( $container->wrap ) . '>';
		} elseif ( ! in_array( 'table' , $container->blocks ) ) {
			$container->wrap['colspan'] = 2;
			$html[] = '<td' . self::attr( $container->wrap ) . '>';
		}

		$html = apply_filters( self::plugin . '/fields/field-start_wrap', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function end_wrap( $container, $output = 'string', $html = array() ) {
		if ( in_array( 'actions', $container->blocks ) ){
			$html[] = self::actions( $container );
		}

		if ( in_array( 'fields', $container->blocks ) && ! in_array( 'table' , $container->blocks ) ){
			$html[] = '</fieldset>';
		}

		if ( in_array( 'description', $container->blocks ) ){
			$html[] = self::description( $container );
		}
		if ( ! in_array( 'table' , $container->blocks ) ){
			$html[] = '</td>';
		}

		$html = apply_filters( self::plugin . '/fields/field-end_wrap', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function label( $container, $output = 'string', $html = array() ) {
		$html[] = '<' . ( false !== strpos(  $container->type, 'meta' ) ? 'td' : 'th' ) . ' scope="row" colspan="1">';

		if ( isset( $container->label ) && false !== $container->label ) {
			$html[] = '<label class="' . self::abbr( 'field-label' ) . '" for="' . self::id( $container->id ) . '">' . $container->label . '</label>';
		}

		$html[] = '</' . ( false !== strpos(  $container->type, 'meta' ) ? 'td' : 'th' ) . '>';

		$html = apply_filters( self::plugin . '/fields/field-label', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function actions( $container, $output = 'string', $html = array() ) {
		if ( empty( $container->actions ) ) {
			return ( 'string' === $output ? '' : array() );
		}

		$html[] = '<div class="' . self::abbr( 'actions' ) . '">';
		foreach ( $container->actions as $action => $label ) {
			$html[] = get_submit_button( $label, 'primary', self::plugin . '[actions][' . $action . ']', false );
		}
		$html[] = '</div>';

		$html = apply_filters( self::plugin . '/fields/field-actions', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function description( $container, $output = 'string', $html = array() ) {
		if ( ! empty( $container->description ) ) {
			$html[] = '<p class="' . self::abbr( 'field-description' ) . '">' . $container->description . '</p>';;
		}

		$html = apply_filters( self::plugin . '/fields/field-description', $html, $container );
		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}


	/******************
	 * Static methods *
	 ******************/

	public static function is_valid_type( $type = false ){
		// a list of valid field types, to prevent screwy behaviour
		return in_array( $type, apply_filters( self::plugin . '/fields/valid_types', self::$valid_types ) );
	}

	public static function name( $indexes = array() ){
		return self::plugin . '[' . implode( '][', (array) $indexes ) . ']';
	}

	public static function id( $id = array(), $container = false ){
		if ( ! is_array( $id ) ){
			$id = (array) $id;
		}
		if ( $container ){
			$id[] = 'container';
		}
		return self::plugin . '-field-' . implode( '-', (array) $id );
	}

	public static function attr( $attributes = array(), $html = array() ) {
		if ( is_scalar( $attributes ) ){
			return false;
		}

		$attributes = (array) $attributes;

		foreach ( $attributes as $key => $value ) {
			if ( is_null( $value ) || false === $value ){
				continue;
			}

			if ( '_' === substr( $key, 0, 1 ) ){
				$key = substr_replace( $key, 'data-', 0, 1 );
			}

			if ( 'class' === $key && ! is_array( $value ) ){
				$value = (array) $value;
			}

			$attr = $key;

			if ( ! is_scalar( $value ) ) {
				if ( 'class' === $key ){
					$value = array_map( array( __CLASS__, 'abbr' ), (array) $value );
					if ( in_array( 'fp-type-button', $value ) ){
						$value[] = 'button';
					}
					$value = array_map( 'sanitize_html_class', $value );
					$value = implode( ' ', $value );
				} else {
					$value = htmlspecialchars( json_encode( $value ), ENT_QUOTES, 'UTF-8' );
				}
			}
			if ( ! is_bool( $value ) || true !== $value ){
				$attr .= '="' . $value . '"';
			}

			$html[ $key ] = $attr;
		}

		return ' ' . implode( ' ', $html );
	}

	public static function parse( $field, &$container = null ){
		if ( is_scalar( $field ) ){
			if ( ! is_string( $field ) ){
				return false;
			}

			$field = (object) array(
				'type' => $field,
			);
		} elseif ( is_array( $field ) ){
			$field = (object) $field;
		}

		if ( ! is_a( $container, __CLASS__ ) ){
			$container = (object) wp_parse_args( $container, self::$default_container );
		}
		if ( ! isset( $container->id ) ) {
			$container->id = (array) self::abbr( uniqid() );
		}

		$field = (object) wp_parse_args( $field, ( ! empty( $container->field ) ? $container->field : array() ) );

		// Setup Private Attributes (_*)
		if ( isset( $field->_id ) ){

		} elseif ( empty( $field->id ) ){
			$field->_id = (array) $container->id;
		} else {
			$field->_id = (array) $field->id;
		}

		if ( isset( $field->_name ) ){

		} elseif ( ! isset( $field->name ) ){
			$field->_name = (array) ( isset( $container->field->name ) ? $container->field->name : $field->_id );
		} else {
			$field->_name = (array) $field->name;
		}

		// Setup Public Attributes
		if ( empty( $field->type ) ){
			$field->type = $container->type;
		}
		$field->_type = $field->type;

		$field->id = self::id( $field->_id );
		$field->name = self::name( $field->_name );

		switch ( $field->type ) {
			case 'heading':
				if ( ! isset( $field->title ) ){
					$field->title = '';
				}

				if ( ! isset( $field->description ) ){
					$field->description = '';
				}

				$container->has_label = false;
				$container->blocks = array( 'actions' );
				break;
			case 'meta':
				if ( ! isset( $container->label ) ){
					$container->label = '';
				}

				$container->has_label = false;
				$container->blocks = array( 'actions' );
				break;
			case 'input':
				# code...
				break;
			case 'text':
				if ( empty( $field->size ) ){
					$field->size = 'medium';
				}
				break;
			case 'number':
				if ( empty( $field->size ) ){
					$field->size = 'tiny';
				}
				break;
			case 'radio':
				unset( $field->size );

				if ( ! isset( $field->options ) ){
					$field->options = array();
				}
				$field->options = (array) $field->options;

				break;
			case 'checkbox':
				unset( $field->size );

				if ( ! isset( $field->options ) ){
					$field->options = array();
				}

				if ( ! is_array( $field->options ) ){
					$field->options = array(
						1 => $field->options,
					);
				}

				break;
			case 'dropdown':
				if ( isset( $field->multiple ) && $field->multiple ){
					$field->type = 'hidden';
				} else {
					if ( ! isset( $field->options ) ){
						$field->options = array();
					}
					$field->options = (array) $field->options;
				}

				break;
			case 'interval':

				break;
			case 'date':
				$field->type = 'text';
				$field->size = 'small';
				break;
		}

		$field = apply_filters( self::plugin . '/fields/field', $field, $container );
		$container = apply_filters( self::plugin . '/fields/container', $container, $field );

		$field = apply_filters( self::plugin . '/fields/field-' . $field->_type, $field, $container );
		$container = apply_filters( self::plugin . '/fields/container-' . $field->_type, $container, $field );

		if ( ! empty( $field->class ) ){
			$field->class = (array) $field->class;
		}
		$field->class[] = 'field';
		$field->class[] = 'type-' . $field->_type;

		if ( ! empty( $field->size ) ){
			$field->class[] = 'size-' . $field->size;
		}

		return $field;
	}

	/*****************
	 * Field Methods *
	 *****************/

	public static function type_input( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$content[] = '<input' . self::attr( $field ) . '/>';

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_button( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_number( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_text( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_hidden( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_date( $field, $container = null, $output = 'string', $html = array() ) {
		return self::type_input( $field, $container, $output, $html );
	}

	public static function type_heading( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$content[] = '<h3>' . $field->title . '</h3>';

		if ( ! empty( $field->description ) ){
			$content[] = '<div class="' . self::abbr( 'field-description' ) . '">' . $field->description . '</div>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_radio( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		foreach ( $field->options as $value => $label ) {
			$checkbox = clone $field;
			$radio->value = $value;

			$content[] = self::type_input( $radio, null, 'string', array() );
			$content[] = '<label class="' . self::abbr( 'field-label' ) . '" for="' . $field->id . '">' . $label . '</label>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_checkbox( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		foreach ( $field->options as $value => $label ) {
			$checkbox = clone $field;
			$checkbox->_id[] = sanitize_html_class( $value );
			$checkbox->value = $value;

			if ( isset( $field->value ) && $field->value === $checkbox->value ){
				$checkbox->checked = true;
			}

			$content[] = self::type_input( $checkbox, null, 'string', array() );
			$content[] = '<label class="' . self::abbr( 'field-label' ) . '" for="' . self::id( $checkbox->_id ) . '">' . $label . '</label>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_dropdown( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		if ( isset( $field->multiple ) && $field->multiple ){
			$content[] = self::type_input( $field, null, 'string', array() );
		} else {
			$content[] = '<select' . self::attr( $field ) . '>';
			$content[] = '<option></option>';
			foreach ( $field->options as $option ) {
				$option = (array) $option;
				$content[] = '<option' . self::attr( $option ) . '>' . esc_attr( $option['text'] ) . '</option>';
			}
			$content[] = '</select>';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_range( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$min = clone $field;
		$min->_id[] = 'min';
		$min->_name[] = 'min';
		$min->type = 'number';
		$min->{'data-type'} = 'min';
		$min->min = 0;
		$min->class = array();
		$min->placeholder = esc_attr__( 'e.g.: 3', self::plugin );

		$max = clone $field;
		$max->_id[] = 'max';
		$max->_name[] = 'max';
		$max->{'data-type'} = 'max';
		$max->type = 'number';
		$max->min = 0;
		$max->class = array();
		$max->disabled = true;
		$max->placeholder = esc_attr__( 'e.g.: 12', self::plugin );

		$content[] = self::type_input( $min, null, 'string', array() );
		$content[] = '<div class="dashicons dashicons-arrow-right-alt2 dashicon-date" style="display: inline-block;"></div>';
		$content[] = self::type_input( $max, null, 'string', array() );

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_meta( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$index = clone $field;
		$index->_id[] = 'index';
		$index->_name[] = 'index';
		$index->type = 'button';
		$index->value = '1';
		$index->disabled = true;
		$index->class = array( 'action-order' );

		$remove = clone $field;
		$remove->_id[] = 'remove';
		$remove->_name[] = 'remove';
		$remove->type = 'button';
		$remove->value = '&minus;';
		$remove->class = array( 'action-remove' );

		$duplicate = clone $field;
		$duplicate->_id[] = 'duplicate';
		$duplicate->_name[] = 'duplicate';
		$duplicate->type = 'button';
		$duplicate->deactive = true;
		$duplicate->value = '&plus;';
		$duplicate->class = array( 'action-duplicate' );

		$table = clone $container;
		$table->blocks = array( 'heading', 'table' );
		$table->heads = array(
			array(
				'class' => 'order-table',
				'html' => self::type_button( $index, null, 'string' ),
			),
			array(
				'class' => 'label-table',
				'html' => '',
			),
			array(
				'class' => 'fields-table',
				'html' => '',
			),
			array(
				'html' => self::type_button( $remove, null, 'string' ) . self::type_button( $duplicate, null, 'string' ),
				'class' => 'actions-table',
			),
		);
		$blocks = array(
			array(
				'html' => '',
				'class' => 'order-table',
			),
			'label', 'fields',
			array(
				'html' => '',
				'class' => 'actions-table',
			),
		);

		$meta_type = clone $container;
		$meta_type->id[] = 'type';
		$meta_type->type .= '_type';
		$meta_type->label = __( 'Type', self::plugin );
		$meta_type->description = __( 'Select a type of the Meta Field', self::plugin );
		$meta_type->class = array( 'meta_type-container' );
		$meta_type->blocks = $blocks;

		$meta_name = clone $container;
		$meta_name->id[] = 'name';
		$meta_name->type .= '_name';
		$meta_name->label = __( 'Name', self::plugin );
		$meta_name->description = __( 'Select the name for Meta Field', self::plugin );
		$meta_name->class = array( 'meta_name-container' );
		$meta_name->blocks = $blocks;

		$meta_conf = clone $container;
		$meta_conf->id[] = 'conf';
		$meta_name->type .= '_conf';
		$meta_conf->label = __( 'Configuration', self::plugin );
		$meta_conf->description = __( '', self::plugin );
		$meta_conf->class = array( 'meta_conf-container' );
		$meta_conf->blocks = $blocks;

		$type = clone $field;
		$type->_id[] = 'type';
		$type->_name[] = 'type';
		$type->type = 'dropdown';
		$type->options = self::get_meta_types();
		$type->class = array( 'meta_type' );
		$type->placeholder = esc_attr__( 'Select a Field type', self::plugin );

		$name = clone $field;
		$name->_id[] = 'name';
		$name->_name[] = 'name';
		$name->type = 'text';
		$name->class = array( 'meta_name' );
		$name->placeholder = esc_attr__( 'Newborn Meta needs a Name, E.g.: _new_image', self::plugin );

		$content[] = $meta_type->build( self::type_dropdown( $type, null, 'string' ) );
		$content[] = $meta_name->build( self::type_text( $name, null, 'string' ) );
		$content[] = $meta_conf->build( '' );

		$content = $table->build( $content );

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		foreach ( $type->options as $key => $ftype ) {
			$is_callable = ( isset( $ftype->template ) && is_callable( $ftype->template ) );
			$html[] = '<script type="text/html" data-rel="' . self::id( $container->id, true ) . '" class="' . self::abbr( 'template-' . $ftype->value ) . '"' . ( $is_callable ? ' data-callable' : '' ) . '>';
			if ( $is_callable ){
				$html[] = call_user_func_array( $ftype->template, array( $field, $ftype ) );
			}
			$html[] = '</script>';
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function get_meta_types(){
		$types = (object) array();

		$types->numbers = array(
			'value' => 'numbers',
			'text' => __( 'Number', self::plugin ),
			'template' => function( $field, $type ){
				$min = clone $field;
				$min->_id = array( 'meta', 'number' );
				$min->_name = array( 'meta', 'number' );
				$min->type = 'range';
				$min->class = array();

				$html[] = self::wrapper( self::type_range( $min, null, 'string' ), $min );

				return implode( "\r\n", $html );
			},
		);

		$types->elements = array(
			'value' => 'elements',
			'text' => __( 'Elements', self::plugin ),
			'template' => function( $field, $type ){
				$tags = clone $field;
				$tags->_id = array( 'meta', 'elements' );
				$tags->_name = array( 'meta', 'elements' );
				$tags->type = 'dropdown';
				$tags->multiple = true;
				$tags->{'data-options'} = array();
				$tags->{'data-tags'} = true;
				$tags->class = array();
				$tags->placeholder = __( 'Type all possible elements (Comma-Separated)', self::plugin );
				$tags->label = __( '', self::plugin );

				$qty = clone $field;
				$qty->_id = array( 'meta', 'qty' );
				$qty->_name = array( 'meta', 'qty' );
				$qty->type = 'range';
				$qty->class = array();
				$qty->label = __( 'Quantity', self::plugin );

				$weight = clone $field;
				$weight->_id = array( 'meta', 'weight' );
				$weight->_name = array( 'meta', 'weight' );
				$weight->type = 'number';
				$weight->class = array();
				$weight->min = 0;
				$weight->max = 100;
				$weight->placeholder = __( 'Eg: 55', self::plugin );
				$weight->label = __( 'Weight', self::plugin );

				$html[] = self::wrapper( self::type_dropdown( $tags, null, 'string' ), $tags );
				$html[] = self::wrapper( self::type_range( $qty, null, 'string' ), $qty );
				$html[] = self::wrapper( self::type_number( $weight, null, 'string' ), $weight );

				return implode( "\r\n", $html );
			},
		);

		$types->letter = array(
			'value' => 'letter',
			'text' => __( 'Letter', self::plugin ),
			'template' => function( $field, $type ){
				$weight = clone $field;
				$weight->_id = array( 'meta', 'weight' );
				$weight->_name = array( 'meta', 'weight' );
				$weight->type = 'number';
				$weight->class = array();
				$weight->min = 0;
				$weight->max = 100;
				$weight->placeholder = __( 'Eg: 55', self::plugin );
				$weight->label = __( 'Weight', self::plugin );

				$html[] = self::wrapper( self::type_number( $weight, null, 'string' ), $weight );

				return implode( "\r\n", $html );
			},
		);

		$types->words = array(
			'value' => 'words',
			'text' => __( 'Words', self::plugin ),
			'template' => function( $field, $type ){
				$qty = clone $field;
				$qty->_id = array( 'meta', 'qty' );
				$qty->_name = array( 'meta', 'qty' );
				$qty->type = 'range';
				$qty->class = array();
				$qty->label = __( 'Quantity', self::plugin );

				$weight = clone $field;
				$weight->_id = array( 'meta', 'weight' );
				$weight->_name = array( 'meta', 'weight' );
				$weight->type = 'number';
				$weight->class = array();
				$weight->min = 0;
				$weight->max = 100;
				$weight->placeholder = __( 'Eg: 55', self::plugin );
				$weight->label = __( 'Weight', self::plugin );

				$html[] = self::wrapper( self::type_range( $qty, null, 'string' ), $qty );
				$html[] = self::wrapper( self::type_number( $weight, null, 'string' ), $weight );

				return implode( "\r\n", $html );
			},
		);

		$types->senteces = array(
			'value' => 'senteces',
			'text' => __( 'Sentences', self::plugin ),
			'template' => function( $field, $type ){
				$qty = clone $field;
				$qty->_id = array( 'meta', 'qty' );
				$qty->_name = array( 'meta', 'qty' );
				$qty->type = 'range';
				$qty->class = array();
				$qty->label = __( 'Quantity', self::plugin );

				$weight = clone $field;
				$weight->_id = array( 'meta', 'weight' );
				$weight->_name = array( 'meta', 'weight' );
				$weight->type = 'number';
				$weight->class = array();
				$weight->min = 0;
				$weight->max = 100;
				$weight->placeholder = __( 'Eg: 55', self::plugin );
				$weight->label = __( 'Weight', self::plugin );

				$html[] = self::wrapper( self::type_range( $qty, null, 'string' ), $qty );
				$html[] = self::wrapper( self::type_number( $weight, null, 'string' ), $weight );

				return implode( "\r\n", $html );
			},
		);

		$types->text = array(
			'value' => 'text',
			'text' => __( 'Text', self::plugin ),
		);

		$types->html = array(
			'value' => 'html',
			'text' => __( 'HTML', self::plugin ),
		);

		$types->lexify = array(
			'value' => 'lexify',
			'text' => __( 'Lexify', self::plugin ),
		);

		$types->asciify = array(
			'value' => 'asciify',
			'text' => __( 'Asciify', self::plugin ),
		);

		$types->regexify = array(
			'value' => 'regexify',
			'text' => __( 'Regexify', self::plugin ),
		);

		$types->person = array(
			'value' => 'person',
			'text' => __( 'Person', self::plugin ),
		);

		$types->address = array(
			'value' => 'address',
			'text' => __( 'Address', self::plugin ),
		);

		$types->phone = array(
			'value' => 'phone',
			'text' => __( 'Phone', self::plugin ),
		);

		$types->date = array(
			'value' => 'date',
			'text' => __( 'Date', self::plugin ),
		);

		$types->email = array(
			'value' => 'email',
			'text' => __( 'Email', self::plugin ),
		);

		$types->domain = array(
			'value' => 'domain',
			'text' => __( 'Domain', self::plugin ),
		);

		$types->ip = array(
			'value' => 'ip',
			'text' => __( 'IP', self::plugin ),
		);

		$types->user_agent = array(
			'value' => 'user_agent',
			'text' => __( 'Browser User Agent', self::plugin ),
		);
		foreach ( $types as $key => $type ) {
			$types->{$key} = (object) $type;
		}

		return apply_filters( self::plugin . '/fields/meta_types', $types );
	}

	public static function type_interval( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		$min = clone $field;
		$min->_id[] = 'min';
		$min->_name[] = 'min';
		$min->type = 'date';
		$min->{'data-type'} = 'min';
		$min->class = array();
		$min->placeholder = esc_attr__( 'yyyy-mm-dd', self::plugin );

		$max = clone $field;
		$max->_id[] = 'max';
		$max->_name[] = 'max';
		$max->type = 'date';
		$max->{'data-type'} = 'max';
		$max->class = array();
		$max->placeholder = esc_attr__( 'yyyy-mm-dd', self::plugin );

		$interval = clone $field;
		$interval->_id[] = 'interval';
		$interval->_name[] = 'interval';
		$interval->type = 'dropdown';
		$interval->class = array();
		$interval->{'data-placeholder'} = esc_attr__( 'Select an Interval', self::plugin );
		$interval->options = Dates::get_intervals();

		$content[] = self::type_dropdown( $interval, null, 'string' );
		$content[] = self::type_date( $min, null, 'string' );
		$content[] = '<div class="dashicons dashicons-arrow-right-alt2 dashicon-date" style="display: inline-block;"></div>';
		$content[] = self::type_date( $max, null, 'string' );

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	public static function type_raw( $field, $container = null, $output = 'string', $html = array() ) {
		$field = self::parse( $field, $container );
		if ( is_scalar( $field ) ){
			return false;
		}

		if ( ! empty( $field->html ) ){
			$content[] = $field->html;
		} else {
			$content = '';
		}

		if ( is_a( $container, __CLASS__ ) ){
			$html[] = $container->build( $content );
		} else {
			$html = $content;
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $html );
		} else {
			return $html;
		}
	}

	/*

	public static function type_textarea( $field, $container = null, $output = 'string' ) {
		if ( is_array( $container ) ){
			$field[] = $this->start_container();
			$field[] = $this->label();
			$field[] = $this->start_wrap();
		}

		$field[] = '<textarea' . $this->attr() . '>' . esc_html( stripslashes( $this->value ) ) . '</textarea>';

		if ( is_array( $container ) ){
			$field[] = $this->end_wrap();
			$field[] = $this->end_container();
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $field );
		} else {
			return $field;
		}
	}

	public static function type_wysiwyg( $field, $container = null, $output = 'string' ) {
		$settings = array(
			'teeny'   => true,
			'wpautop' => true,
		);
		ob_start();
		wp_editor( html_entity_decode( ( $this->value ) ), $this->name, $settings );
		$editor = ob_get_clean();

		if ( is_array( $container ) ){
			$field[] = $this->start_container();
			$field[] = $this->label();
			$field[] = $this->start_wrap();
		}

		$field[] = $editor;

		if ( is_array( $container ) ){
			$field[] = $this->end_wrap();
			$field[] = $this->end_container();
		}

		if ( 'string' === $output ){
			return implode( "\r\n", $field );
		} else {
			return $field;
		}
	}

	 */

} // end class