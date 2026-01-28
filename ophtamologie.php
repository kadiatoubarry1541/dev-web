<?php
require_once 'config/database_functions.php';

// Récupérer les médecins ophtalmologues depuis la base de données
$medecins_ophtalmologie = [];
try {
    // Essayer d'abord avec "Ophtalmologie"
    $medecins_ophtalmologie = getMedecinsBySpecialisation('Ophtalmologie');
    // Si aucun résultat, essayer avec "Ophtalmologie" dans la base
    if (empty($medecins_ophtalmologie)) {
        $medecins_ophtalmologie = getMedecinsBySpecialisation('Ophtalmologie');
    }
} catch (Exception $e) {
    // En cas d'erreur, continuer avec un tableau vide
    $medecins_ophtalmologie = [];
}

// Récupérer toutes les images du dossier image/auft
$dossier_auft = 'image/auft';
$images_auft = [];

if (is_dir($dossier_auft)) {
    $fichiers = scandir($dossier_auft);
    foreach ($fichiers as $fichier) {
        if ($fichier != '.' && $fichier != '..' && $fichier != 'imageDEV') {
            $extension = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'jfif', 'webp'])) {
                $images_auft[] = $dossier_auft . '/' . $fichier;
            }
        }
    }
}

// Image par défaut pour ophtalmologie (première image du dossier auft si disponible)
$image_default_ophtalmologie = !empty($images_auft) ? $images_auft[0] : 'image/medecin/i44.jfif';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="keywords" content="" />
	<meta name="author" content="" />
	<meta name="robots" content="" />
	<meta name="description" content="" />
	<meta property="og:title" content="MediCo. - Ophtalmologie" />
	<meta property="og:description" content="" />
	<meta property="og:image" content="" />
	<meta name="format-detection" content="telephone=no">
	
	<!-- FAVICONS ICON - utiliser le logo du site -->
	<link rel="icon" href="image/1.jpeg" type="image/jpeg" />
	<link rel="shortcut icon" href="image/1.jpeg" type="image/jpeg" />
	
	<!-- PAGE TITLE HERE -->
	<title>MediCo. - Ophtalmologie</title>
	
	<!-- MOBILE SPECIFIC -->
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<!--[if lt IE 9]>
	<script src="assets/js/html5shiv.min.js"></script>
	<script src="assets/js/respond.min.js"></script>
	<![endif]-->
	
	<!-- STYLESHEETS -->
	<link rel="stylesheet" type="text/css" href="assets/css/plugins.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.min.css">
	<link class="skin" rel="stylesheet" type="text/css" href="assets/css/skin/skin-1.css">
	<link rel="stylesheet" type="text/css" href="assets/css/templete.min.css">
	<!-- Revolution Slider Css -->
	<link rel="stylesheet" type="text/css" href="assets/plugins/revolution/revolution/css/layers.css">
	<link rel="stylesheet" type="text/css" href="assets/plugins/revolution/revolution/css/settings.css">
	<link rel="stylesheet" type="text/css" href="assets/plugins/revolution/revolution/css/navigation.css">
	<!-- Revolution Navigation Style -->
	<style>
		.doctor-card {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			padding: 0;
			overflow: hidden;
			transition: transform 0.3s ease, box-shadow 0.3s ease;
		}
		.doctor-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 5px 20px rgba(0,0,0,0.15);
		}
		.doctor-image {
			width: 100%;
			height: 400px;
			object-fit: cover;
			object-position: center top;
		}
		.doctor-info {
			padding: 20px;
			text-align: center;
		}
		.doctor-name {
			font-size: 18px;
			font-weight: 700;
			color: #002939;
			margin-bottom: 8px;
		}
		.doctor-specialty {
			font-size: 14px;
			color: #666;
			margin-bottom: 12px;
		}
		.doctor-rating {
			margin: 12px 0;
		}
		.doctor-rating .fa-star {
			color: #FFD700;
			font-size: 16px;
		}
		.doctor-contact {
			font-size: 13px;
			color: #666;
			margin: 8px 0;
		}
		.doctor-contact i {
			margin-right: 5px;
			color: #002939;
		}
		.btn-rdv {
			background: #002939;
			color: #fff;
			border: none;
			padding: 10px 20px;
			border-radius: 6px;
			font-weight: 600;
			width: 100%;
			margin-top: 15px;
			transition: background 0.3s ease;
		}
		.btn-rdv:hover {
			background: #004d66;
			color: #fff;
		}
	</style>
</head>
<body id="bg">

<div id="loading-area"></div><div class="page-wraper">
    <!-- header -->
    <?php require_once'partials/entete.php';?>
    <!-- header END -->
	<!-- Content -->
    <div class="page-content">	
		<!-- Slider -->
        <div class="main-slider style-two default-banner">
            <div class="tp-banner-container">
                <div class="tp-banner" >
                    <div id="dz_rev_slider_4_wrapper" class="rev_slider_wrapper fullwidthbanner-container" data-alias="news-gallery36" data-source="gallery" style="margin:0px auto;background-color:#ffffff;padding:0px;margin-top:0px;margin-bottom:0px;">
                        <!-- START REVOLUTION SLIDER 5.3.0.2 fullwidth mode -->
                        <div id="slider_03" class="rev_slider fullwidthabanner" style="display:none;" data-version="5.3.0.2">
                            <ul>
                                <!-- SLIDE  -->
                                <li data-index="rs-100" data-transition="parallaxvertical" data-slotamount="default" data-hideafterloop="0" data-hideslideonmobile="off"  data-easein="default" data-easeout="default" data-masterspeed="default"  data-thumb="<?php echo htmlspecialchars(!empty($images_auft[0]) ? $images_auft[0] : 'image/auft/o.jfif'); ?>"  data-rotate="0"  data-fstransition="fade" data-fsmasterspeed="1500" data-fsslotamount="7" data-saveperformance="off"  data-title="OPHTALMOLOGIE" data-param1="" data-param2="" data-param3="" data-param4="" data-param5="" data-param6="" data-param7="" data-param8="" data-param9="" data-param10="">
                                    <!-- MAIN IMAGE -->
                                    <img src="<?php echo htmlspecialchars(!empty($images_auft[0]) ? $images_auft[0] : 'image/auft/o.jfif'); ?>"  alt=""  data-bgposition="center center" data-bgfit="cover" data-bgrepeat="no-repeat" data-bgparallax="10" class="rev-slidebg" data-no-retina>
                                    <!-- LAYERS -->
                                    <div class="tp-caption tp-shape tp-shapewrapper " id="slide-100-layer-1" 
									data-x="['center','center','center','center']" 
									data-hoffset="['0','0','0','0']" 
									data-y="['middle','middle','middle','middle']" 
									data-voffset="['0','0','0','0']" 
									data-width="full" data-height="full" 
									data-whitespace="nowrap" 
									data-type="shape" 
									data-basealign="slide" 
									data-responsive_offset="off" 
									data-responsive="off" 
									data-frames='[{"from":"opacity:0;","speed":1000,"to":"o:1;","delay":0,"ease":"Power4.easeOut"},{"delay":"wait","speed":1000,"to":"opacity:0;","ease":"Power4.easeOut"}]' 
									data-textAlign="['left','left','left','left']" 
									data-paddingtop="[0,0,0,0]" 
									data-paddingright="[0,0,0,0]" 
									data-paddingbottom="[0,0,0,0]" 
									data-paddingleft="[0,0,0,0]" 
									style="z-index: 2;background-color:rgba(0, 0, 0, 0.4);border-color:rgba(0, 0, 0, 0);border-width:0px; "> </div>
                                    <!-- LAYER NR. 2 -->
                                    <div class="tp-caption Newspaper-Title  tp-resizeme" 
										id="slide-100-layer-3" 
										data-x="['left','left','left','left']" 
										data-hoffset="['50','50','50','30']" 
										data-y="['top','top','top','top']" 
										data-voffset="['220','220','240','100']" 
										data-fontsize="['45','45','45','28']"
										data-lineheight="['85','85','55','35']"
										data-width="['1000','1000','1000','420']"
										data-height="none"
										data-whitespace="normal"
							 
										data-type="text" 
										data-responsive_offset="on" 

										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;s:inherit;e:inherit;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;s:inherit;e:inherit;","ease":"Power3.easeInOut"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[10,10,10,10]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 6; white-space: normal;text-transform:left; font-weight:bold; line-height:50px; font-family: 'rubik', sans-serif; color:#fff;"><span class="text-primary">PRENEZ SOIN DE VOTRE</span> VUE</div>
                                    <!-- LAYER NR. 3 -->
                                    <div class="tp-caption Newspaper-Title   tp-resizeme" 
										id="slide-100-layer-4" 
										data-x="['left','left','left','left']" 
										data-hoffset="['50','50','50','30']" 
										data-y="['top','top','top','top']" 
										data-voffset="['310','310','310','145']" 
										data-fontsize="['16','15','14','13']"
										data-lineheight="['26','25','24','23']"
										data-width="['700','600','600','420']"
										data-height="none"
										data-whitespace="normal"
							 
										data-type="text" 
										data-responsive_offset="on" 

										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;s:inherit;e:inherit;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;s:inherit;e:inherit;","ease":"Power3.easeInOut"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[10,10,10,10]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 6; white-space: normal;text-transform:left; line-height:80px; color:#fff; font-family:'rubik', sans-serif">Texte d'exemple pour présenter nos services d'ophtalmologie. Nous offrons des soins complets pour préserver et améliorer votre vision.</div>
                                    <!-- LAYER NR. 4 -->
                                    <div class="tp-caption radius-xl" 
										id="slide-100-layer-5" 
										data-x="['left','left','left','left']" data-hoffset="['53','53','50','30']" 
										data-y="['top','top','top','top']" data-voffset="['410','410','410','250']" 
										data-width="none"
										data-height="none"
										data-whitespace="nowrap"
										data-responsive_offset="on" 
										data-responsive="off"
										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;","ease":"Power3.easeInOut"},{"frame":"hover","speed":"300","ease":"Power1.easeInOut","to":"o:1;rX:0;rY:0;rZ:0;z:0;","style":"c:rgba(0, 0, 0, 1.00);bg:rgba(255, 255, 255, 1.00);bc:rgba(255, 255, 255, 1.00);bw:1px 1px 1px 1px;"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[0,0,0,0]"
										data-paddingleft="[0,0,0,0]"
										style="z-index: 8;"> <a href="#" class="site-button button-lg radius-xl">En Savoir Plus </a> 
									</div>
                                    <div class="tp-caption radius-xl" 
										id="slide-100-layer-6" 
										data-x="['left','left','left','left']" data-hoffset="['230','230','200','180']" 
										data-y="['top','top','top','top']" data-voffset="['410','410','410','250']" 
										data-width="none"
										data-height="none"
										data-whitespace="nowrap"
										data-responsive_offset="on" 
										data-responsive="off"
										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;","ease":"Power3.easeInOut"},{"frame":"hover","speed":"300","ease":"Power1.easeInOut","to":"o:1;rX:0;rY:0;rZ:0;z:0;","style":"c:rgba(0, 0, 0, 1.00);bg:rgba(255, 255, 255, 1.00);bc:rgba(255, 255, 255, 1.00);bw:1px 1px 1px 1px;"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[0,0,0,0]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 8;"> <a href="#" class="site-button  button-lg white radius-xl">Nos Solutions</a> </div>
                                </li>
                                <!-- SLIDE  -->
								<!-- SLIDE  -->
                                <li data-index="rs-200" data-transition="parallaxvertical" data-slotamount="default" data-hideafterloop="0" data-hideslideonmobile="off"  data-easein="default" data-easeout="default" data-masterspeed="default"  data-thumb="<?php echo htmlspecialchars(!empty($images_auft[1]) ? $images_auft[1] : 'image/auft/O444.jfif'); ?>"  data-rotate="0"  data-fstransition="fade" data-fsmasterspeed="1500" data-fsslotamount="7" data-saveperformance="off"  data-title="OPHTALMOLOGIE" data-param1="" data-param2="" data-param3="" data-param4="" data-param5="" data-param6="" data-param7="" data-param8="" data-param9="" data-param10="">
                                    <!-- MAIN IMAGE -->
                                    <img src="<?php echo htmlspecialchars(!empty($images_auft[1]) ? $images_auft[1] : 'image/auft/O444.jfif'); ?>"  alt=""  data-bgposition="center center" data-bgfit="cover" data-bgrepeat="no-repeat" data-bgparallax="10" class="rev-slidebg" data-no-retina>
                                    <!-- LAYERS -->
                                    <div class="tp-caption tp-shape tp-shapewrapper " id="slide-200-layer-1" 
									data-x="['center','center','center','center']" 
									data-hoffset="['0','0','0','0']" 
									data-y="['middle','middle','middle','middle']" 
									data-voffset="['0','0','0','0']" 
									data-width="full" data-height="full" 
									data-whitespace="nowrap" 
									data-type="shape" 
									data-basealign="slide" 
									data-responsive_offset="off" 
									data-responsive="off" 
									data-frames='[{"from":"opacity:0;","speed":1000,"to":"o:1;","delay":0,"ease":"Power4.easeOut"},{"delay":"wait","speed":1000,"to":"opacity:0;","ease":"Power4.easeOut"}]' 
									data-textAlign="['left','left','left','left']" 
									data-paddingtop="[0,0,0,0]" 
									data-paddingright="[0,0,0,0]" 
									data-paddingbottom="[0,0,0,0]" 
									data-paddingleft="[0,0,0,0]" 
									style="z-index: 2;background-color:rgba(0, 0, 0, 0.4);border-color:rgba(0, 0, 0, 0);border-width:0px; "> </div>
                                    <!-- LAYER NR. 2 -->
                                    <div class="tp-caption Newspaper-Title   tp-resizeme" 
										id="slide-200-layer-3" 
										data-x="['left','left','left','left']" 
										data-hoffset="['50','50','50','30']" 
										data-y="['top','top','top','top']" 
										data-voffset="['220','220','240','100']" 
										data-fontsize="['45','45','45','28']"
										data-lineheight="['85','85','55','35']"
										data-width="['1000','1000','1000','420']"
										data-height="none"
										data-whitespace="normal"
							 
										data-type="text" 
										data-responsive_offset="on" 

										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;s:inherit;e:inherit;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;s:inherit;e:inherit;","ease":"Power3.easeInOut"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[10,10,10,10]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 6; white-space: normal;text-transform:left; font-weight:bold; line-height:50px; font-family: 'rubik', sans-serif; color:#fff;"><span class="text-primary">MEILLEUR SERVICE </span>OPHTALMOLOGIQUE</div>
                                    <!-- LAYER NR. 3 -->
                                    <div class="tp-caption Newspaper-Title   tp-resizeme" 
										id="slide-200-layer-4" 
										data-x="['left','left','left','left']" 
										data-hoffset="['50','50','50','30']" 
										data-y="['top','top','top','top']" 
										data-voffset="['310','310','310','145']" 
										data-fontsize="['16','15','14','13']"
										data-lineheight="['26','25','24','23']"
										data-width="['700','600','600','420']"
										data-height="none"
										data-whitespace="normal"
							 
										data-type="text" 
										data-responsive_offset="on" 

										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;s:inherit;e:inherit;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;s:inherit;e:inherit;","ease":"Power3.easeInOut"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[10,10,10,10]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 6; white-space: normal;text-transform:left; line-height:80px; color:#fff; font-family:'rubik', sans-serif">Texte d'exemple pour présenter nos services d'ophtalmologie. Nous offrons des soins complets pour préserver et améliorer votre vision.</div>
                                    <!-- LAYER NR. 4 -->
                                    <div class="tp-caption radius-xl" 
										id="slide-200-layer-5" 
										data-x="['left','left','left','left']" data-hoffset="['53','53','50','30']" 
										data-y="['top','top','top','top']" data-voffset="['410','410','410','250']"
										data-width="none"
										data-height="none"
										data-whitespace="nowrap"
										data-responsive_offset="on" 
										data-responsive="off"
										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;","ease":"Power3.easeInOut"},{"frame":"hover","speed":"300","ease":"Power1.easeInOut","to":"o:1;rX:0;rY:0;rZ:0;z:0;","style":"c:rgba(0, 0, 0, 1.00);bg:rgba(255, 255, 255, 1.00);bc:rgba(255, 255, 255, 1.00);bw:1px 1px 1px 1px;"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[0,0,0,0]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 8;"> <a href="#" class="site-button button-lg radius-xl">En Savoir Plus </a> </div>
                                    <div class="tp-caption radius-xl " 
										id="slide-200-layer-6" 
										data-x="['left','left','left','left']" data-hoffset="['230','230','200','180']" 
										data-y="['top','top','top','top']" data-voffset="['410','410','410','250']" 
										data-width="none"
										data-height="none"
										data-whitespace="nowrap"
										data-responsive_offset="on" 
										data-responsive="off"
										data-frames='[{"from":"y:[-100%];z:0;rX:0deg;rY:0;rZ:0;sX:1;sY:1;skX:0;skY:0;","mask":"x:0px;y:0px;","speed":1500,"to":"o:1;","delay":1000,"ease":"Power3.easeInOut"},{"delay":"wait","speed":1000,"to":"auto:auto;","mask":"x:0;y:0;","ease":"Power3.easeInOut"},{"frame":"hover","speed":"300","ease":"Power1.easeInOut","to":"o:1;rX:0;rY:0;rZ:0;z:0;","style":"c:rgba(0, 0, 0, 1.00);bg:rgba(255, 255, 255, 1.00);bc:rgba(255, 255, 255, 1.00);bw:1px 1px 1px 1px;"}]'
										data-textAlign="['left','left','left','left']"
										data-paddingtop="[0,0,0,0]"
										data-paddingright="[0,0,0,0]"
										data-paddingbottom="[0,0,0,0]"
										data-paddingleft="[0,0,0,0]"

										style="z-index: 8;"> <a href="#" class="site-button  button-lg white radius-xl">Nos Solutions</a> </div>
                                </li>
                                <!-- SLIDE  -->
                                
                            </ul>
                            <div class="tp-bannertimer tp-bottom bg-primary" style="height: 5px; "></div>
                        </div>
                    </div>
                    <!-- END REVOLUTION SLIDER -->
                </div>
            </div>
        </div>
        <!-- Slider END -->
		
		
		<!-- Our Services -->
		<div class="section-full content-inner bg-white">
			<div class="container">
				<div class="section-head text-center">
                    <h3 class="h3 text-uppercase">Pouvons-Nous Vous <span class="text-primary"> Aider ?</span></h3>
					<p>Nos services ophtalmologiques complets pour prendre soin de votre vision et de vos yeux.</p>
                </div>
				<div class="row">
					<div class="col-md-4 col-lg-4">
						<div class="icon-bx-wraper p-a30 center p-t0">
							<div class="icon-md text-primary m-b20"> 
								<a href="" class="icon-cell"><i class="fa fa-eye" style="font-size: 48px;"></i></a>
							</div>
							<div class="icon-content">
								<h5 class="dez-tilte text-uppercase"> Examens de la Vue </h5>
								<p>Examens complets de la vision pour détecter et traiter les problèmes oculaires. </p>
								<a href="#" class="site-button radius-xl">En Savoir Plus</a>
							</div>
						</div>
					</div>
					<div class="col-md-4 col-lg-4">
						<div class="icon-bx-wraper p-a30 center p-t0">
							<div class="icon-md text-primary m-b20"> 
								<a href="#" class="icon-cell"><i class="fa fa-stethoscope" style="font-size: 48px;"></i></a>
							</div>
							<div class="icon-content">
								<h5 class="dez-tilte text-uppercase"> Diagnostic Précis</h5>
								<p>Technologies avancées pour un diagnostic précis des troubles oculaires. </p>
								<a href="#" class="site-button radius-xl">En Savoir Plus</a>
							</div>
						</div>
					</div>
					<div class="col-md-4 col-lg-4">
						<div class="icon-bx-wraper p-a30 center p-t0">
							<div class="icon-md text-primary m-b20"> 
								<a href="#" class="icon-cell"><i class="fa fa-heartbeat" style="font-size: 48px;"></i></a>
							</div>
							<div class="icon-content">
								<h5 class="dez-tilte text-uppercase">Traitements Spécialisés </h5>
								<p>Soins et traitements adaptés à vos besoins ophtalmologiques spécifiques. </p>
								<a href="#" class="site-button radius-xl">En Savoir Plus</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	    <!-- Our Services END-->
	    <!-- Our Awesome Services -->
        <div class="section-full awesome-services-4 bg-gray-md content-inner-1 rounded">
            <div class="container">
                <div class="section-content">
                    <div class="row dzseth bg-primary">
                        <div class="col-lg-3 col-md-6 bg-primary p-lr0 box-services text-white">
                            <div class="p-a30 box-services-content">
								<h2 class="m-t0 m-b15 text-uppercase">Examens Oculaires</h2>
								<p>Examens complets de la vision avec des équipements de pointe pour évaluer votre santé oculaire.</p>
								<div class="m-t20"> <a href="javascript:;" class="site-button white radius-xl">En Savoir Plus</a> </div>
							</div>
                        </div>
                        <div class="col-lg-3 col-md-6 p-a0"> 
							<img src="<?php echo htmlspecialchars(!empty($images_auft[0]) ? $images_auft[0] : 'image/auft/o.jfif'); ?>" alt="Examens Oculaires" class="img-cover"/> 
						</div>
                        <div class="col-lg-3 col-md-6 bg-primary p-lr0 box-services text-white">
                            <div class="p-a30 box-services-content">
								<h2 class="m-t0 m-b15 text-uppercase">Traitements Spécialisés</h2>
								<p>Soins ophtalmologiques personnalisés pour tous types de troubles visuels et oculaires.</p>
								<div class="m-t20"> <a href="javascript:;" class="site-button white radius-xl">En Savoir Plus</a> </div>
							</div>
                        </div>
						<div class="col-lg-3 col-md-6 p-a0 "> 
							<img src="<?php echo htmlspecialchars(!empty($images_auft[1]) ? $images_auft[1] : 'image/auft/O444.jfif'); ?>" alt="Traitements Ophtalmologiques" class="img-cover"/>
						</div>
                    </div>
					<div class="row dzseth bg-primary">
						<div class="col-lg-3 col-md-6 bg-primary p-lr0 box-services text-white">
                            <div class="p-a30 box-services-content">
								<h2 class="m-t0 m-b15 text-uppercase">Technologies Avancées</h2>
								<p>Utilisation des dernières technologies pour un diagnostic et un traitement précis.</p>
								<div class="m-t20"> <a href="javascript:;" class="site-button white radius-xl">En Savoir Plus</a> </div>
							</div>
                        </div>
                        <div class="col-lg-3 col-md-6 bg-primary p-lr0 box-services text-white" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                            <div class="p-a30 box-services-content text-center">
								<i class="fa fa-microscope" style="font-size: 60px; margin-bottom: 20px; display: block;"></i>
								<h2 class="m-t0 m-b15 text-uppercase">Équipements Modernes</h2>
								<p>Technologies de pointe pour des examens précis et des diagnostics fiables.</p>
								<div class="m-t20"> <a href="javascript:;" class="site-button white radius-xl">En Savoir Plus</a> </div>
							</div>
                        </div>
                        <div class="col-lg-3 col-md-6 bg-primary p-lr0 box-services text-white">
                            <div class="p-a30 box-services-content">
								<h2 class="m-t0 m-b15 text-uppercase">OPHTALMOLOGIE </h2>
								<p>Service complet dédié à la santé de vos yeux et à l'amélioration de votre vision.</p>
								<div class="m-t20"> <a href="javascript:;" class="site-button white radius-xl">En Savoir Plus</a> </div>
							</div>
                        </div>
                        <div class="col-lg-3 col-md-6 bg-primary p-lr0 box-services text-white" style="background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);">
                            <div class="p-a30 box-services-content text-center">
								<i class="fa fa-user-md" style="font-size: 60px; margin-bottom: 20px; display: block;"></i>
								<h2 class="m-t0 m-b15 text-uppercase">Équipe Qualifiée</h2>
								<p>Professionnels expérimentés dédiés à votre santé visuelle et oculaire.</p>
								<div class="m-t20"> <a href="javascript:;" class="site-button white radius-xl">En Savoir Plus</a> </div>
							</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Our Awesome Services END -->
        <!-- Why Choose Us  -->
        <div class="section-full bg-img-fix content-inner overlay-primary-dark text-white " style="background-image:url(images/background/bg1.jpg);">
            <div class="container">
                <div class="row">
					<div class="col-lg-3 col-md-6 col-sm-6 col-6">
						<div class="counter-style-1 m-b30">
							<div class="">
								<i class="icon flaticon-bar-chart text-white"></i>
								<span class="counter">7652</span>
							</div>
							<span class="counter-text">Projets terminés</span>
						</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6 col-6">
						<div class="counter-style-1 m-b30">
							<div class="">
								<i class="icon flaticon-social text-white"></i>
								<span class="counter">4562</span>
							</div>
							<span class="counter-text">Patients satisfaits</span>
						</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6 col-6">
						<div class="counter-style-1 m-b30">
							<div class="">
								<i class="icon flaticon-file text-white"></i>
								<span class="counter">3569</span>
							</div>
							<span class="counter-text">Questions traitées</span>
						</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6 col-6">
						<div class="counter-style-1 m-b30">
							<div class="">
								<i class="icon flaticon-pencil text-white"></i>
								<span class="counter">2089</span>
							</div>
							<span class="counter-text">Cafés servis</span>
						</div>
					</div>
				</div>
            </div>
        </div>
        <!-- Why Choose Us END -->
		<div class="section-full content-inner-2 bg-white">
            <div class="container">
				<div class="row">
					<div class="col-xl-8 col-lg-12 m-b30 align-self-end">
						<div class="row">
							<div class="col-lg-6 col-md-6">
								<div class="icon-bx-wraper left m-b30">
									<div class="icon-lg text-primary radius"> <a href="#" class="icon-cell"><i class="fa fa-medkit"></i></a> </div>
									<div class="icon-content">
										<h2 class="dez-tilte m-b5">Conseil Médical </h2>
										<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet 
											dolore magna.</p>
									</div>
								</div>
								<div class="icon-bx-wraper left m-b30">
									<div class="icon-lg text-primary radius"> <a href="#" class="icon-cell"><i class="fa fa-ambulance"></i></a> </div>
									<div class="icon-content">
										<h2 class="dez-tilte m-b5">Services d'Urgence</h2>
										<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet 
											dolore magna.</p>
									</div>
								</div>
							</div>
							<div class="col-lg-6 col-md-6">
								<div class="icon-bx-wraper left m-b30">
									<div class="icon-lg text-primary radius"> <a href="#" class="icon-cell"><i class="fa fa-user-md"></i></a> </div>
									<div class="icon-content">
										<h2 class="dez-tilte m-b5">Médecins Qualifiés </h2>
										<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet 
											dolore magna.</p>
									</div>
								</div>
								<div class="icon-bx-wraper left m-b30">
									<div class="icon-lg text-primary radius"> <a href="#" class="icon-cell"><i class="fa fa-plus-square"></i></a> </div>
									<div class="icon-content">
										<h2 class="dez-tilte m-b5">Centre de Rééducation</h2>
										<p>Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet 
											dolore magna.</p>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-lg-4 d-lg-none d-xl-block">
						<div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); padding: 40px; border-radius: 10px; height: 100%; display: flex; align-items: center; justify-content: center;">
							<i class="fa fa-eye" style="font-size: 120px; color: white; opacity: 0.3;"></i>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- Nos Médecins Ophtalmologues -->
		<div class="section-full content-inner bg-white" style="padding: 60px 0;">
            <div class="container">
				<div class="section-head text-center">
                    <h3 class="h3 text-uppercase">Nos <span class="text-primary">Médecins Ophtalmologues</span></h3>
					<p>Rencontrez notre équipe de médecins ophtalmologues expérimentés, dédiés à votre santé visuelle.</p>
                </div>
                <div class="row">
                    <?php if (!empty($medecins_ophtalmologie)): ?>
                        <?php foreach ($medecins_ophtalmologie as $medecin): 
                            // Gérer le chemin de la photo de profil
                            $photo_profil = '';
                            if (!empty($medecin['Photo_profil'])) {
                                // Construire le chemin complet du fichier
                                $chemin_photo = $medecin['Photo_profil'];
                                
                                // Si le chemin ne commence pas par uploads/, l'ajouter
                                if (strpos($chemin_photo, 'uploads/') !== 0) {
                                    // Si c'est juste un nom de fichier, l'ajouter au dossier profiles
                                    if (strpos($chemin_photo, '/') === false) {
                                        $chemin_photo = 'uploads/profiles/' . $chemin_photo;
                                    }
                                }
                                
                                // Vérifier si le fichier existe réellement
                                if (file_exists(__DIR__ . '/' . $chemin_photo)) {
                                    $photo_profil = $chemin_photo;
                                } else {
                                    // Si le fichier n'existe pas, essayer avec uploads/profiles/ directement
                                    $chemin_alternatif = 'uploads/profiles/' . basename($medecin['Photo_profil']);
                                    if (file_exists(__DIR__ . '/' . $chemin_alternatif)) {
                                        $photo_profil = $chemin_alternatif;
                                    }
                                }
                            }
                            
                            // Utiliser la photo de profil si disponible, sinon l'image par défaut
                            $image_finale = $photo_profil ? $photo_profil : $image_default_ophtalmologie;
                        ?>
                        <div class="col-lg-4 col-md-6 col-sm-6 m-b30">
                            <div class="doctor-card">
                                <img src="<?php echo htmlspecialchars($image_finale); ?>" 
                                     alt="Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?>" 
                                     class="doctor-image"
                                     onerror="this.src='<?php echo htmlspecialchars($image_default_ophtalmologie); ?>'">
                                <div class="doctor-info">
                                    <h4 class="doctor-name">Dr. <?php echo htmlspecialchars($medecin['Prénom_med'] . ' ' . $medecin['Nom_med']); ?></h4>
                                    <p class="doctor-specialty"><?php echo htmlspecialchars($medecin['Spécialisation_med']); ?></p>
                                    <div class="doctor-rating">
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                        <i class="fa fa-star"></i>
                                    </div>
                                    <?php if (!empty($medecin['Email_med'])): ?>
                                        <p class="doctor-contact">
                                            <i class="fa fa-envelope"></i> <?php echo htmlspecialchars($medecin['Email_med']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($medecin['Tel_med'])): ?>
                                        <p class="doctor-contact">
                                            <i class="fa fa-phone"></i> <?php echo htmlspecialchars($medecin['Tel_med']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <a href="rendez-vous.php?medecin=<?php echo $medecin['id_med']; ?>" class="btn btn-rdv">
                                        Prendre RDV
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php if (!empty($images_auft)): ?>
                            <?php foreach ($images_auft as $index => $image_auft): ?>
                                <div class="col-lg-4 col-md-6 col-sm-6 m-b30">
                                    <div class="doctor-card">
                                        <img src="<?php echo htmlspecialchars($image_auft); ?>" 
                                             alt="Ophtalmologie - Image <?php echo $index + 1; ?>" 
                                             class="doctor-image"
                                             onerror="this.src='<?php echo htmlspecialchars($image_default_ophtalmologie); ?>'">
                                        <div class="doctor-info">
                                            <h4 class="doctor-name">Ophtalmologie</h4>
                                            <p class="doctor-specialty">Service Ophtalmologique</p>
                                            <div class="doctor-rating">
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 30px; border-radius: 8px;">
                                    <i class="fa fa-info-circle" style="font-size: 48px; color: #0c5460; margin-bottom: 15px;"></i>
                                    <h4 style="color: #0c5460; margin-bottom: 15px;">Aucun médecin ophtalmologue disponible</h4>
                                    <p style="font-size: 16px;">
                                        Il n'y a actuellement aucun médecin ophtalmologue approuvé dans la base de données.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Nos Médecins Ophtalmologues END -->
		<!-- Galerie Ophtalmologie -->
		<div class="section-full content-inner bg-white">
            <div class="container">
				<div class="section-head text-center">
                    <h3 class="h3 text-uppercase">Galerie <span class="text-primary">Ophtalmologie</span></h3>
					<p>Découvrez nos équipements et installations dédiés aux soins ophtalmologiques.</p>
                </div>
				<div class="row">
					<?php if (!empty($images_auft)): ?>
						<?php foreach ($images_auft as $index => $image_auft): ?>
							<div class="col-md-4 col-lg-3 col-sm-6 m-b30">
								<div class="dez-box">
									<div class="dez-media">
										<a href="<?php echo htmlspecialchars($image_auft); ?>" class="dez-img-overlay4 btn-block mfp-image">
											<img src="<?php echo htmlspecialchars($image_auft); ?>" alt="Ophtalmologie - Image <?php echo $index + 1; ?>" class="img-cover" style="width: 100%; height: 300px; object-fit: cover;">
										</a>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else: ?>
						<div class="col-12">
							<div class="alert alert-info text-center">
								<p>Aucune image trouvée dans le dossier image/auft</p>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<!-- Galerie Ophtalmologie END -->
		<!-- Testimonials -->
		<div class="section-full content-inner bg-gray" style="background-image:url(images/background/bg3.png); background-position:center; background-repeat:no-repeat;">
			<div class="container">
				<div class="section-head text-center ">
					<h3 class="h3 text-uppercase">Nos <span class="text-primary">Témoignages</span></h3>
					<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry has been the industry's standard dummy text ever since the been when an unknown printer.</p>
				</div>
				<div class="section-content m-b30 row">
					<div class="testimonial-box style1  item-center owl-loaded owl-theme owl-carousel owl-none mfp-gallery owl-dots-black-full">
						<div class="item">
							<div class="testimonial-8">
								<div class="testimonial-text">
									<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the when an printer took a galley of type and scrambled it to make [...]</p>
								</div>
								<div class="testimonial-detail clearfix">
									<div class="testimonial-pic radius shadow" style="background: #007bff; display: flex; align-items: center; justify-content: center;">
										<i class="fa fa-user" style="font-size: 50px; color: white;"></i>
									</div>
									<h5 class="testimonial-name m-t0 m-b5">Patient Satisfait</h5> 
									<span>Patient</span> 
								</div>
							</div>
						</div>
						<div class="item">
							<div class="testimonial-8">
								<div class="testimonial-text ">
									<div class="video-testimonial">
										<img src="<?php echo htmlspecialchars(!empty($images_auft[2]) ? $images_auft[2] : 'image/auft/O444.jfif'); ?>" alt="Ophtalmologie" style="width: 100%; height: auto;"/>
										<div class="video-testimonial-play">
											<a href="https://www.youtube.com/watch?v=xqUM6DayZcw" class="popup-youtube video" title="Vidéo Ophtalmologie"><i class="ti-control-play"></i></a>
										</div>	
									</div>
								</div>
								<div class="testimonial-detail clearfix">
									<div class="testimonial-pic radius shadow" style="background: #007bff; display: flex; align-items: center; justify-content: center;">
										<i class="fa fa-user" style="font-size: 50px; color: white;"></i>
									</div>
									<h5 class="testimonial-name m-t0 m-b5">Patient Satisfait</h5> 
									<span>Patient</span> 
								</div>
							</div>
						</div>
						<div class="item">
							<div class="testimonial-8">
								<div class="testimonial-text">
									<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the when an printer took a galley of type and scrambled it to make [...]</p>
								</div>
								<div class="testimonial-detail clearfix">
									<div class="testimonial-pic radius shadow" style="background: #007bff; display: flex; align-items: center; justify-content: center;">
										<i class="fa fa-user" style="font-size: 50px; color: white;"></i>
									</div>
									<h5 class="testimonial-name m-t0 m-b5">Patient Satisfait</h5> 
									<span>Patient</span> 
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- Testimoniyal End -->
        <!-- Client logo -->
        <div class="section-full dez-we-find bg-white p-t50 p-b50 ">
            <div class="container">
                <div class="section-content">
                    <div class="owl-carousel client-logo-carousel mfp-gallery gallery owl-btn-center-lr owl-btn-1 primary">
                        <div class="item">
                            <div class="ow-client-logo">
                                <div class="client-logo"><a href="#"><img src="images/client-logo/logo1.jpg" alt=""></a></div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ow-client-logo">
                                <div class="client-logo"> <a href="#"><img src="images/client-logo/logo2.jpg" alt=""></a> </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ow-client-logo">
                                <div class="client-logo"> <a href="#"><img src="images/client-logo/logo1.jpg" alt=""></a> </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ow-client-logo">
                                <div class="client-logo"> <a href="#"><img src="images/client-logo/logo3.jpg" alt=""></a> </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ow-client-logo">
                                <div class="client-logo"> <a href="#"><img src="images/client-logo/logo4.jpg" alt=""></a> </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="ow-client-logo">
                                <div class="client-logo"> <a href="#"><img src="images/client-logo/logo3.jpg" alt=""></a> </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Client logo END -->
    </div>
    <!-- Content END-->
    <!-- Footer -->
    <?php require_once'partials/footer.php';?>
    <!-- Footer END-->
    <!-- scroll top button -->
    <button class="scroltop fa fa-chevron-up" ></button>
</div>
<!-- JavaScript  files ========================================= -->
<script src="assets/js/jquery.min.js"></script><!-- JQUERY.MIN JS -->
<script src="assets/plugins/bootstrap/js/popper.min.js"></script><!-- BOOTSTRAP.MIN JS -->
<script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script><!-- BOOTSTRAP.MIN JS -->
<script src="assets/plugins/bootstrap-select/bootstrap-select.min.js"></script><!-- FORM JS -->
<script src="assets/plugins/bootstrap-touchspin/jquery.bootstrap-touchspin.js"></script><!-- FORM JS -->
<script src="assets/plugins/magnific-popup/magnific-popup.js"></script><!-- MAGNIFIC POPUP JS -->
<script src="assets/plugins/counter/waypoints-min.js"></script><!-- WAYPOINTS JS -->
<script src="assets/plugins/counter/counterup.min.js"></script><!-- COUNTERUP JS -->
<script src="assets/plugins/imagesloaded/imagesloaded.js"></script><!-- IMAGESLOADED -->
<script src="assets/plugins/masonry/masonry-3.1.4.js"></script><!-- MASONRY -->
<script src="assets/plugins/masonry/masonry.filter.js"></script><!-- MASONRY -->
<script src="assets/plugins/owl-carousel/owl.carousel.js"></script><!-- OWL SLIDER -->
<script src="assets/js/custom.min.js"></script><!-- CUSTOM FUCTIONS  -->
<script src="assets/js/dz.carousel.min.js"></script><!-- SORTCODE FUCTIONS  -->
<script src="assets/js/dz.ajax.js"></script><!-- CONTACT JS  -->
<script src="assets/plugins/switcher/js/switcher.min.js"></script><!-- SWITCHER JS  -->
<!-- revolution JS FILES -->
<script src="assets/plugins/revolution/revolution/js/jquery.themepunch.tools.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/jquery.themepunch.revolution.min.js"></script>
<!-- Slider revolution 5.0 Extensions  (Load Extensions only on Local File Systems !  The following part can be removed on Server for On Demand Loading) -->
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.actions.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.carousel.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.kenburn.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.layeranimation.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.navigation.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.parallax.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.slideanims.min.js"></script>
<script src="assets/plugins/revolution/revolution/js/extensions/revolution.extension.video.min.js"></script>
<script src="assets/js/rev.slider.js"></script>
<script >
jQuery(document).ready(function() {
	'use strict';
	dz_rev_slider_3();
});	/*ready*/
</script>
</body>
</html>
