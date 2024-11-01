<?php
$this->wpchef_me_warning();

add_thickbox();
$me = $this->wpchef_me();
?>

<div class="wrap recipes-install-page">
	<h1>
		Add Recipes
		<a href="<?php echo $this->url_add?>&amp;upload" class="page-title-action add-new-h2">Upload Recipe</a>
		<a href="<?php echo $this->url('create', '', true)?>" class="page-title-action add-new-h2"><?php _E('Create Recipe', 'wpchef')?></a>
		<?php $this->wpchef_me_badge() ?>
	</h1>
	<div class="wp-filter">
		<ul class="filter-links">
			<li>
				<a href="<?php echo $this->url_add?>"<?php if($tab==''):?> class="current"<?php endif ?>>
					<?php esc_html_e('All', 'wpchef') ?>
				</a>
			<?php if ( !$me || !empty( $me['admin_access'] ) ): ?>
			<li>
				<a href="<?php echo $this->url_add?>&amp;tab=my" class="wpchef_auth_only<?php echo $tab=='my'?' current':''?>">
					<?php esc_html_e('My Recipes', 'wpchef') ?>
				</a>
			<?php endif ?>
			<li>
				<a href="<?php echo $this->url_add?>&amp;tab=purchased" class="wpchef_auth_only<?php echo $tab=='purchased'?' current':''?>">
					<?php esc_html_e('Shared with me', 'wpchef') ?>
					<?php if ( $me && isset( $me['count_purchased'] ) ): ?>
					(<?php echo esc_html( $me['count_purchased'] ) ?>)
					<?php endif ?>
				</a>
			<li>
		</ul>

		<form class="search-form search-plugins" method="get">
			<input type="hidden" name="page" value="recipe-install" />
			<label>
				<span class="screen-reader-text">Search Recipes</span>
				<input name="s" value="<?php echo esc_attr(stripslashes(@$_GET['s']))?>" class="wp-filter-search" placeholder="Search Recipes" type="search">
			</label>
			<input id="search-submit" class="button screen-reader-text" value="Search Plugins" type="submit">
		</form>
	</div>
	
	<div class="clear" />
	
	<p>
		<?php if ( $tab == 'my' && $me): ?>
		<?php printf(
			esc_html__('Only the recipes created by you (%s) and saved to %s are displayed here.', 'wpchef'),
			'<b>'.esc_html( $me['display_name'] ).'</b>',
			sprintf('<a href="%swp-admin/edit.php?post_type=recipe" target="_blank">%s</a>', $this->server, esc_html( $this->servername ) )
		) ?>
		<?php endif ?>
	</p>
	
	<?php if ( $data ): ?>
	<?php 	if ( $info && $info['pages'] > 1 ): ?>
	<div class="tablenav top">
		<div class="tablenav-pages">
			<span class="displaying-num"><?php echo number_format($info['results'])?> items</span>
			<span class="pagination-links">
				<?php if ( $page > 2 ): ?>
				<a class="first-page" href="<?php echo esc_attr($this->url('install'))?>">
					<span class="screen-reader-text">First page</span>
					<span aria-hidden="true">«</span>
				</a>
				<?php else: ?>
				<span class="tablenav-pages-navspan" aria-hidden="true">«</span>
				<?php endif ?>
				<?php if ( $page > 1 ): ?>
				<a class="prev-page" href="<?php echo esc_attr($this->url('install'))?>&amp;p=2">
					<span class="screen-reader-text">Previous page</span>
					<span aria-hidden="true">‹</span>
				</a>
				<?php else: ?>
				<span class="tablenav-pages-navspan" aria-hidden="true">‹</span>
				<?php endif ?>
				<form class="paging-input" action="<?php echo esc_attr('admin.php')?>" method="get">
					<input type="hidden" name="page" value="recipe-install" />
					<label for="current-page-selector" class="screen-reader-text">Current Page</label>
					<input class="current-page" id="current-page-selector" name="paged" value="<?php echo $page?>" size="2" aria-describedby="table-paging" type="text"> of <span class="total-pages"><?php echo $info['pages']?></span>
				</form>
				<?php if ( $page < $info['pages'] ): ?>
				<a class="next-page" href="<?php echo esc_attr($this->url('install'))?>&amp;p=<?php echo ($page+1)?>">
					<span class="screen-reader-text">Next page</span>
					<span aria-hidden="true">›</span>
				</a>
				<?php else: ?>
				<span class="tablenav-pages-navspan" aria-hidden="true">›</span>
				<?php endif ?>
				<?php if ( $page + 1 < $info['pages'] ): ?>
				<a class="last-page" href="<?php echo esc_attr($this->url('install'))?>&amp;p=<?php echo $info['pages']?>">
					<span class="screen-reader-text">Last page</span>
					<span aria-hidden="true">»</span>
				</a>
				<?php else: ?>
				<span class="tablenav-pages-navspan" aria-hidden="true">»</span>
				<?php endif ?>
			</span>
		</div>
		<br class="clear">
	</div>
	<?php 	endif ?>
	<div class="wp-list-table widefat plugin-install recipe-install">
		<div id="the-list">
		<?php foreach ( $data as $recipe ): $current = $this->get_recipe_by_id( $recipe['wpchef_id'],  $recipe['slug'] ) ?>
			<div class="plugin-card<?php echo $current['installed']?' active':''?>" data-wpchef_id="<?php echo $recipe['wpchef_id']?>" data-slug="<?php echo esc_attr($recipe['slug'])?>">
				<div class="plugin-card-top">
					<?php $detail_url = '#TB_inline?width=600&amp;height=350&amp;inlineId=recipe_detail_'.$recipe['slug'] ?>
					<?php if ( $recipe['thumbnail'] ): ?>
					<a href="<?php echo $detail_url?>" class="thickbox plugin-icon"><img src="<?php echo $recipe['thumbnail']?>"></a>
					<?php endif ?>
					<div class="name column-name">
						<h3><a href="<?php echo $detail_url?>" class="thickbox recipe-name"><?php echo esc_html($recipe['name'])?></a></h3>
					</div>
					<div class="action-links">
						<ul class="plugin-action-buttons">
							<li>
							<?php $install_url = $this->url( 'install', $recipe['wpchef_id'], true ) . '&amp;nonce='.wp_create_nonce( 'install_recipe_'.$recipe['wpchef_id'] ) ?>
							<?php if ( $recipe['allow_access'] ): ?>
								<?php if ( !$current['uploaded'] ): ?>
								<a class="install-now button recipe-ajax-install" href="<?php echo $install_url?>">Install Now</a>
								<?php elseif ( !$current['installed'] && version_compare($recipe['version'], $current['version'], '>') ): ?>
								<a class="update-now button recipe-ajax-install" href="<?php echo $install_url?>">Update Now</a>
								<?php elseif ( $current['installed'] && version_compare($recipe['version'], $current['installed'], '>') ): ?>
								<a class="activate-now button" href="<?php echo $install_url?>" data-upload_sec="<?php echo wp_create_nonce('wpchef_upload_'.$current['slug'])?>">Update Now</a>
								<?php elseif ( !$current['installed'] ): ?>
								<a class="activate-now button" href="#">Activate</a>
								<?php else: ?>
								<a class="disabled button">Installed</a>
								<?php endif ?>
							<?php elseif ( $recipe['can_paid'] ): ?>
								<a class="buy-now button wpchef_auth_only" href="<?php echo $install_url?>">
									Buy ($<?php echo esc_html($recipe['amount'])?>)
									<?php if( $recipe['allow_user'] && $me ): ?>
									<i class="wpchef-hint fa fa-question-circle" title="<?php printf( __('You (the %s WPChef user) already have this recipe purchased, but for another domain. You can buy it for this domain now.', 'wpchef'), esc_attr($me['display_name']) ) ?>"></i>
									<?php endif ?>
								</a>
							<?php else: ?>
								<a class="disabled button">Unavailable</a>
							<?php endif ?>
							<li><a href="<?php echo $detail_url?>" class="thickbox">More Details</a>
						</ul>
					</div>
					<div class="desc column-description">
						<p><?php echo esc_html($recipe['description'])?></p>
						<p class="authors">
							<cite>By <a target="_blank"  href="<?php echo esc_attr($recipe['author_profile_url'])?>"><?php echo esc_html($recipe['author'])?></a></cite>
							<?php if ( $recipe['admin_access'] ): ?>
							<strong>(you)</strong>
							<?php endif ?>
						</p>
					</div>
				</div>
				<div class="plugin-card-bottom">
					
					<div class="vers column-rating">
						<?php $rating = round( (float)$recipe['rating'], 1 ) ?>
						<div class="star-rating" title="<?php echo $rating?> rating based on <?php echo (int)$recipe['rating_users']?> ratings">
							<span class="screen-reader-text"><?php echo $rating?> rating based on <?php echo (int)$recipe['rating_users']?> ratings</span>
							<?php $i = 0; while( $rating-$i > 0.5 ): ?>
							<div class="star star-full"></div>
							<?php $i++; endwhile ?>
							<?php if ( $rating-$i > 0 ): $i++ ?>
							<div class="star star-half"></div>
							<?php endif ?>
							<?php while( $i < 5 ): $i++ ?>
							<div class="star star-empty"></div>
							<?php endwhile ?>
						</div>
						<span class="num-ratings">(<?php echo (int)$recipe['rating_users']?>)</span>
					</div>
					<div class="column-updated">
						<strong>Last Updated:</strong>
						<span><?php printf( __( '%s ago' ), human_time_diff( strtotime($recipe['updated']) ) ); ?></span>
						<p class="column-ingredients">
							<?php if ( $tab = 'my' ): $this->recipe_things($recipe, $recipe['slug']) ?>
							<i class="fa <?php echo $recipe['status_icon'] ?> wpchef-hint" title="<?php echo esc_attr($recipe['status_hint'] ) ?>"></i>
							<?php endif ?>
							<strong>Ingredients:</strong>
							<?php echo  count($recipe['ingredients']) ?>
						</p>
					</div>
					<?php if ( !empty( $recipe['private']) && $tab != 'my' ): ?>
					<div class="column-updated">
						<strong>Status:</strong> Private
					</div>
					<?php endif ?>
					<div class="column-downloaded">
						<?php echo $recipe['installs_human']?> <?php _e( $recipe['installs'] == 1 ? 'Active Install' : 'Active Installs' , 'wpchef' ) ?>
					</div>
					<!--
					<div class="column-compatibility">
						<span class="compatibility-compatible"><strong>Compatible</strong> with your version of WordPress</span>
					</div>
					-->
				</div>
				<div id="recipe_detail_<?php echo $recipe['slug']?>" style="display:none">
					<div class="recipe-detail">
						<h2><?php echo esc_html($recipe['name'])?></h2>
						<div class="content">
							<div class="ingredients">
								<ul class="inside">
									<li>
										<strong>Version:</strong> <?php echo esc_html($recipe['version'])?>
									<li>
										<strong>Author:</strong>
										<a href="<?php echo esc_url($recipe['author_profile_url'])?>" target="_blank" ><?php echo esc_html($recipe['author'])?></a>
									<li>
										<strong><?php _e('Last Updated', 'wpchef') ?>:</strong>
										<?php printf( __( '%s ago' ), human_time_diff( strtotime($recipe['updated']) ) ); ?>
									<?php if ( $recipe['phpversion'] ): ?>
									<li>
										<strong><?php _e('Requires PHP Version', 'wpchef') ?>:</strong>
										<?php echo esc_html($recipe['phpversion'])?>
									<?php endif ?>
									<li>
										<strong><?php _e( $recipe['installs'] == 1 ? 'Active Install' : 'Active Installs' , 'wpchef' ) ?>:</strong>
										<?php echo esc_html($recipe['installs_human'])?>
									<?php if ( empty($recipe['private']) ): ?>
									<li>
										<a target="_blank" href="<?php echo $this->server.'recipe/'.$recipe['slug']?>">WPChef.org Recipe Page »</a>
									<?php endif ?>
									<?php if ( $recipe['uri'] ): ?>
									<li>
										<a href="<?php echo esc_url($recipe['uri'])?>" target="_blank" ><?php _e('Recipe Homepage', 'wpchef') ?></a>
									<?php endif ?>
								</ul>
								
								<div class="ratings-box inside">
									<strong><?php _e('Avarage Rating', 'wpchef') ?></strong>
									<?php $rating = round( (float)$recipe['rating'], 1 ) ?>
									<div class="star-rating" title="<?php echo $rating?> rating based on <?php echo (int)$recipe['rating_users']?> ratings">
										<span class="screen-reader-text"><?php echo $rating?> rating based on <?php echo (int)$recipe['rating_users']?> ratings</span>
										<?php $i = 0; while( $rating-$i > 0.5 ): ?>
										<div class="star star-full"></div>
										<?php $i++; endwhile ?>
										<?php if ( $rating-$i > 0 ): $i++ ?>
										<div class="star star-half"></div>
										<?php endif ?>
										<?php while( $i < 5 ): $i++ ?>
										<div class="star star-empty"></div>
										<?php endwhile ?>
									</div>
									<span class="num-ratings"><?php printf(__('(based on %d ratings)', 'wpchef'), $recipe['rating_users'])?></span>
									<div class="rating-counts">
										<?php for( $i = 5; $i > 0; $i-- ): ?>
										<div class="ratig-count-item">
											<div class="rating-column-value"><?php echo number_format($i)?> stars</div>
											<div class="rating-column-percent"><div style="width:<?php echo round($recipe['rating_counts'][$i]*100/max(1,$recipe['rating_users']), 1)?>%"></div></div>
											<div class="rating-column-count"><?php echo (int)$recipe['rating_counts'][$i]?></div>
										</div>
										<?php endfor ?>
									</div>
								</div>
							</div>
							<?php echo wp_kses_post( $recipe['details'] )?>
							<h4><?php _e('Ingredients', 'wpchef') ?></h4>
							<div class="inside">
								<?php if ( $recipe['ingredients'] ): ?>
								<ul>
									<?php foreach( $recipe['ingredients'] as $ingredient ): $ingredient = $this->ingredient->normalize( $ingredient ) ?>
									<li><?php echo $ingredient['title']?>
									<?php endforeach ?>
								</ul>
								<?php else: ?>
								Empty recipe
								<?php endif ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach ?>
		</div>
	</div>
	<script>
	jQuery(function($){
		function install_click()
		{
			var error = 'Connection error';
			var button = $(this);
			button.addClass( 'disabled updating-message' );
			
			$.post( ajaxurl, {
				action: 'wpchef_recipe_install',
				sec: <?php echo json_encode(wp_create_nonce('wpchef_recipe_install'))?>,
				id: button.closest('.plugin-card').data('wpchef_id')
			})
			.done( function( data ){
				if ( data && data.success )
				{
					button
						.text('Activate')
						.attr('href', data.data )
						.attr('class', 'activate-now button' )
						.removeClass( 'disabled updating-message')
						.click();
					
					return;
				}
				
				if ( data && data.error )
					error = data.error;
				
				fail();
			})
			.always( function() {
			} )
			.fail( fail );
			
			function fail() {
				window.alert( error );
				button.removeClass( 'disabled updating-message');
			}
			
			return false;
		}
		$('.recipe-install').on('click', '.recipe-ajax-install', install_click );
		
		$('.recipe-install').on('click', '.activate-now', function(){
			var btn = $(this);
			
			
			wpchef.activate_modal( {
				slug: btn.closest('.plugin-card').data('slug'),
				name: btn.closest('.plugin-card').find('.recipe-name').text(),
				upload: btn.data('upload_sec'),
				callback: function( installed ){
					if ( installed )
						btn.text('Installed').attr('disabled', 'disabled');
				}
			} );
			
			return false;
		} );

	});
	</script>
	<?php else: ?>
	<div class="no-plugin-results">
		<?php echo $tab ? 'No recipes found.' : 'No recipes match your request.'?>
	</div>
	<?php endif ?>
</div>

<?php wp_print_request_filesystem_credentials_modal() ?>
