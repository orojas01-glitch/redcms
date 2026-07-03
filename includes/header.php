<!--<header>
    <div class="menuBox clearfix">
        <div class="container">
            <div class="row">
                <article class="col-lg-12 col-md-12 col-sm-12">
                    
                </article>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <section class="col-lg-12 col-md-12 col-sm-12">
                <article>
                    <h1 class="navbar-brand navbar-brand_"><a href="index.html"><img src="img/logo.png" alt=""></a></h1>
                    <div>
                        <img src="img/head_icon.png" alt="">
                        <a href="#">Tel: +1 959 552 5975</a>
                    </div>
                </article>
            </section>
        </div>
    </div>
</header>-->

<header>
	 <div class="menuBox clearfix">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-4 col-sm-4">
                    
				<?php
                $db= new connection(DBHOST, DBUSER, DBPASS, DBNAME);
                $result = $db->query("SELECT * FROM RED_Advanced WHERE Language='".language."' AND Item='Website_Header'");
                //echo ($result->num_rows);
                $result_counter = $result->num_rows;
                
                if($result->num_rows > 0) 
                {
                    $info = mysqli_fetch_assoc($result); 
                    echo $info['Content'];
                }
                ?>
                </div>
                <div class="col-lg-8 col-md-8 col-sm-8">
                <?php
                $mainmenu=new main_menu();
                $mainmenu->menu();
                ?>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            
                <article>
                

                <!--<ul class="menu responsive-menu">
                        <li class="current"><a href="index.html">About Us</a></li>
                        <li><a href="index-1.html">Portfolio</a>
                            <ul>
                                <li><a href="#">Illustration</a></li>
                                <li><a href="#">Web design</a>
                                    <ul>
                                        <li><a href="#">Dolore Ipsu</a></li>
                                        <li><a href="#">Lorem Come</a></li>
                                        <li class="last-item"><a href="#">Consetern</a></li>
                                    </ul>
                                </li>
                                <li class="last-item"><a href="#">3D models</a></li>
                            </ul>
                        </li>
                        <li><a href="index-2.html">Clients</a></li>
                        <li class="last-item"><a href="index-3.html">Contacts</a></li>
                    </ul>-->
                    
                </article>
            </section>
        </div>
    </div>
</header>
<div class="global indent">
        
				<?php
                $feature_slider=new feature_slider();
                $feature_slider->slider();
    
                ?>
            
   			




