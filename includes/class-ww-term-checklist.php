<?php
/**
 * Checklist walker. Same items as core, with our field name and a unique id.
 *
 * @package WW\HarrimanProjects
 */

namespace WW\HarrimanProjects;

defined( 'ABSPATH' ) || exit;

class Term_Checklist_Walker extends \Walker_Category_Checklist {

	/**
	 * Checkbox name, including the trailing brackets.
	 *
	 * @var string
	 */
	private $input_name;

	/**
	 * Prefix so each template panel has its own element ids.
	 *
	 * @var string
	 */
	private $id_prefix;

	/**
	 * Disable the boxes when this template panel is hidden.
	 *
	 * @var bool
	 */
	private $force_disabled;

	/**
	 * @param string $input_name Checkbox name.
	 * @param string $id_prefix  Id prefix.
	 * @param bool   $disabled   Whether the boxes start disabled.
	 */
	public function __construct( $input_name, $id_prefix, $disabled ) {
		$this->input_name     = $input_name;
		$this->id_prefix      = $id_prefix;
		$this->force_disabled = (bool) $disabled;
	}

	/**
	 * One checkbox. Checked terms stay where name order put them.
	 *
	 * @param string  $output            Markup.
	 * @param \WP_Term $data_object      Term.
	 * @param int     $depth             Depth.
	 * @param array   $args              Walker args.
	 * @param int     $current_object_id Current object.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		unset( $depth, $current_object_id );
		$category = $data_object;
		if ( ! $category instanceof \WP_Term ) {
			return;
		}
		$args     = (array) $args;
		$taxonomy = empty( $args['taxonomy'] ) ? 'category' : (string) $args['taxonomy'];
		$selected = ! empty( $args['selected_cats'] ) ? array_map( 'intval', (array) $args['selected_cats'] ) : array();
		$checked  = in_array( (int) $category->term_id, $selected, true );
		$id       = $this->id_prefix . 'in-' . $taxonomy . '-' . $category->term_id;
		$item_id  = $this->id_prefix . $taxonomy . '-' . $category->term_id;

		$output .= "\n<li id='" . esc_attr( $item_id ) . "'>";
		$output .= '<label class="selectit ww-terms__choice">';
		$output .= '<input value="' . esc_attr( (string) $category->term_id ) . '" type="checkbox" name="' . esc_attr( $this->input_name ) . '" id="' . esc_attr( $id ) . '"';
		$output .= checked( $checked, true, false );
		$output .= disabled( $this->force_disabled, true, false );
		$output .= ' /> ';
		$output .= esc_html( $category->name );
		$output .= '</label>';
	}
}
