<?php

/*
 * This file is part of the CCDNComponent BBCodeBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/> 
 * 
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Created by Reece Fowell 
 * <me at reecefowell dot com> 
 * <reece at codeconsortium dot com>
 * Created on 17/12/2011
 *
 * Note: use of ENT_SUBSTITUTE in htmlentities requires PHP 5.4.0, and so
 * PHP versions below won't use it, so it was commented out, and can be
 * uncommented if you are using PHP 5.4.0 and above only.
*/

namespace CCDNComponent\BBCodeBundle\Component\Engine;

use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * 
 * @author Reece Fowell <reece@codeconsortium.com> 
 * @version 1.0
 */
class Parser
{
	
	
	/**
	 *
	 * @access private
	 */
	protected $parser_state_flags = array(
		'use_pre_tag' => false,
		'use_pre_tag_child' => null,
		'use_nested' => true,
		'use_nested_child' => null,
		'use_parse_geshi' => false,
		'use_parse_geshi_child' => null,
		'use_parse_geshi_parent' => null,
	);



	/**
	 *
	 * @access private
	 * @param array $symbol_tree, array $symbol, string $tag
	 */
	private function put_param_in_context(&$symbol_tree, &$symbol, &$tag)
	{					
		$param = null;
		
		if (array_key_exists('ref_child', $symbol))
		{
			if (array_key_exists('tag_param', $symbol_tree[$symbol['ref_child']]))
			{
				$param = $symbol_tree[$symbol['ref_child']]['tag_param'];
			}
		}
		
		if (array_key_exists('ref_parent', $symbol))
		{			
			if (array_key_exists('tag_param', $symbol_tree[$symbol['ref_parent']]))
			{
				$param = $symbol_tree[$symbol['ref_parent']]['tag_param'];				
			}
		}
		
		// Any param in current context takes priority and overrides previous param.
		if (array_key_exists('tag_param', $symbol))
		{
			$param = $symbol['tag_param'];
		}
		
		if ($param)		
		{
			$tag = str_replace('{{param}}', htmlentities($param, ENT_QUOTES), $tag);
		}
	}
	

	
	/**
	 *
	 * @access private
	 * @param string $tag, string $lang
	 * @return string
	 */
	private function parse_geshi($tag, $lang)
	{
		$geshi = new \Geshi_Geshi($tag, $lang);


		$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS, 1);

		$geshi->set_header_type(GESHI_HEADER_PRE_TABLE);
// 		$geshi->set_line_style('background: #1f1f1f; color: #fff;', 'margin-left: 10px; background: #1f1f1f; color: #fff;');
		
		$geshi->enable_classes();
		$geshi->set_overall_class('bb_code_overall');
		
//		echo '<pre>' . $geshi->get_stylesheet() . '</pre><hr>'; 
/*		$geshi->set_keyword_group_style();
		$geshi->set_keyword_group_style();
		$geshi->set_keyword_group_style();
		$geshi->set_keyword_group_style();*/
//		$geshi->set_overall_class('bb_tag_code');

		return $geshi->parse_code();
	}
	
	
	
	/**
	 *
	 * @access public
	 * @param array $symbol_tree, array $lexemes
	 * @return string $html
	 */
	public function parse(&$symbol_tree, &$lexemes)
	{
		$html = '';

		$use_pre_tag =& $this->parser_state_flags['use_pre_tag'];					// This tags html wraps its content in a <pre> tag, so we don't convert \n to <br> as a result.
		$use_pre_tag_child =& $this->parser_state_flags['use_pre_tag_child'];		// reference to the tag that initiated this <pre> tag state.
		
		$use_parse_geshi =& $this->parser_state_flags['use_parse_geshi'];
		$use_parse_geshi_parent =& $this->parser_state_flags['use_parse_geshi_parent'];
		
		$last_tag_content = "";
		
		for ($symbol_key = 0; $symbol_key < count($symbol_tree); $symbol_key++)
		{
			$symbol =& $symbol_tree[$symbol_key];

			if (is_array($symbol))
			{
				if (array_key_exists('lexeme_key', $symbol))
				{
					$lexeme =& $lexemes[$symbol['lexeme_key']];
					
					if (array_key_exists('validation_token', $symbol))
					{
						$tag = $lexeme['symbol_html'][$symbol['token_key']];
						
						// here we are only concerned with the opening tag, and
						// wether it contains a parameter in the opening tag.
						if ($symbol['token_key'] == 0)
						{
							if (array_key_exists('use_pre_tag', $lexeme))
							{
								if ($lexeme['use_pre_tag'] == true)
								{
									if ($use_pre_tag == false)
									{
										$use_pre_tag = true;
										$use_pre_tag_child = $symbol_tree[$symbol['ref_child']];
									}
								}
							}
							
							if (array_key_exists('parse_geshi', $lexeme))						
							{
								if ($lexeme['parse_geshi'] == true)
								{										
									if ($use_parse_geshi == false)
									{
										$use_parse_geshi = true;
//										$use_parse_geshi_child = $symbol_tree[$symbol['ref_child']];
										$use_parse_geshi_parent = $symbol;
										
									}
								}
							}						
						} else {
							//
							// closing tag stuff
							//
							
							// remove any special state flags for closing tags that match prior opened ones.
							if ($use_pre_tag_child['validation_token'] == $symbol['validation_token'])
							{
								$use_pre_tag = false;
								$use_pre_tag_child = null;
							}
							
							if ($use_parse_geshi_parent['validation_token'] == $symbol['validation_token'])
							{
								$use_parse_geshi = false;
								$use_parse_geshi_parent = null;
							}
						}

						$this->put_param_in_context($symbol_tree, $symbol, $tag);
						
					} else {
						// tag has no validation key, so change it from the html token to the lookup str.
						$tag = $symbol['lookup_str'];
					}
				
					$html .= $tag;
				
					continue;
				} else {
					if (count($symbol_tree[$symbol_key]) > 0)
					{
						$html .= $this->parse($symbol_tree[$symbol_key] , $lexemes);
						
						continue;
					}
				}
			} else {
				// non tag related, content only just plain
				// old text or garbled invalid bb code tags.
				$tag = $symbol;
			}
			
			//if ($use_parse_geshi == true)
			//{
			//	if (array_key_exists('tag_param', $use_parse_geshi_parent))
			//	{
			//		$lang = $use_parse_geshi_parent['tag_param'];
			//	} else {
			//		if (array_key_exists('tag_param', $symbol_tree[$use_parse_geshi_parent['ref_child']]))
			//		{
			//			$lang = $symbol_tree[$use_parse_geshi_parent['ref_child']]['tag_param'];
			//		}
			//	}	
			//	
			//	if (strlen($tag) > 1 && $lang)
			//	{
			//		$html .= $this->parse_geshi($tag, $lang);	
			//	}
			//	
			//	continue;
			//}
						
			if ($use_pre_tag == true)
			{
				$html .= htmlentities($tag, ENT_QUOTES);
			} else {
				$html .= nl2br(htmlentities($tag, ENT_QUOTES));
			}
		}

		return $html;
	}

}