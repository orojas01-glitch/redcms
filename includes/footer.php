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
<?php require_once __DIR__ . '/public_render_helpers.php'; ?>
<!--footer-->
<footer>
    <div class="container">
        <div class="row">
		<?php
        $db = new connection(DBHOST, DBUSER, DBPASS, DBNAME);
        $info = red_public_advanced_item($db->connection, 'Website_Footer');
        if ($info) {
            echo $info['Content'];
        }
        $db->close();
        ?>
        </div>
        <p align="center" style="font-size:10px; padding:20px"><a href="http://www.red-sphere.com" target="_blank" style="text-decoration:none"><font color="#CCCCCC">web by</font><br /><img src="/admin/images/red-tm.png" /><br /><font color="#CC0000">Red </font> <font color="#CCCCCC">Sphere</font></a></p><br />
<br />
        </div>
    </div>
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
