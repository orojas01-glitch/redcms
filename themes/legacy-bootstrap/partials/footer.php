<?php
/**
 * Red Sphere - Unique php CMS
 * @version: 1.0 - (2012/02/25)
 * @version: 4.0 - (2025/03/06)
 * @requires linux v1.2.2 or later 
 * @author Oscar Rojas
 * Examples and documentation at: http://red-sphere.tv/documentation/ 
 * Licensed under MIT licence:
 *   http://www.opensource.org/licenses/mit-license.php
**/
?>
<?php
if (!isset($redThemeFooterContext)
    || !is_array($redThemeFooterContext)
    || !array_key_exists('customHtml', $redThemeFooterContext)
) {
    throw new RuntimeException('Legacy footer context is unavailable.');
}
?>
<!--footer-->
<footer>
    <div class="container">
        <div class="row">
		<?php
        echo $redThemeFooterContext['customHtml'];
        ?>
        </div>
        </div>
    </div>
    <?php red_public_render_red_sphere_credit($redThemeFooterContext); ?>
  <!-- {%FOOTER_LINK} -->
</footer>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 
    <script>
	// Get the button:
	let mybutton = document.getElementById("myBtn");

	// When the user scrolls down 20px from the top of the document, show the button
	window.onscroll = function() {scrollFunction()};

	function scrollFunction() {
	if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
	mybutton.style.display = "block";
	} else {
	mybutton.style.display = "none";
	}
	}

	// When the user clicks on the button, scroll to the top of the document
	function topFunction() {
	document.body.scrollTop = 0; // For Safari
	document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
	} 
	</script>
