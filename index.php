<?php

// This bit ensures that all output is GZip compressed, saving bandwidth

if( substr_count( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ], 'gzip' ) ) { ob_start( "ob_gzhandler" ); } else {	ob_start(); }

session_start();

/** Install the app if necessary, by checking if the install folder exists and if so installing  **/
if ( file_exists( "install" ) ) { header( "Location: install/install.php" ); }

require_once( "./libs/DOMTemplate/domtemplate.php" );
require_once( "./Constants.php" );

require_once( "./User.class.php" );
require_once( "./Token.class.php" );
require_once( "./Article.class.php" );
require_once( "./Comment.class.php" );

require_once( "./markdown.php" );
require_once( "./kses.php" );

$allowed = array('b' => array(),
                 'i' => array(),
                 'a' => array('href' => 1, 'title' => 1),
                 'p' => array('align' => 1),
                 'br' => array(), 
                 'h2' => array());

{ // page building variables
$index_page = Constants::HTML_TEMPLATES_DIR."/index.html";
$article_page = Constants::HTML_TEMPLATES_DIR."/article.html";

@session_start();

/* Default template */
$template = DOMTemplate::fromFile($article_page);;

$url = '';						// set to your sites URL, may or may not be useful
$name = 'Ibrahim';
$proffesion = 'web developer';
	
$adminEmail = "";				// all "contact site admin" links will point to this

$HTMLEmailheaders = 'MIME-Version: 1.0' . "\r\n" .
					'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
					'From: ' . $adminEmail . "\r\n" .
					'X-Mailer: PHP/' . phpversion();

$output = '';

$pageTitle = '';				// set the pages title here

$pageHeader = '<!DOCTYPE html>
<html>
	<head>
		<title>' . $pageTitle . '</title>
		<link type="text/css"
		      rel="stylesheet"
		      href="./styles/blog.css.php">
	</head>
	<body>
		<div class="wrapper">
			<div class="header">
			</div>
			<div class="body">
				<div class="sideColumn">
					<p>Hi there, my name&apos;s ' . $name . ' and I am a ' . $proffesion . ' from Nairobi, Kenya</p>';
				
$pageFooter = '
				</div>
			</div>
			<div class="footer"></div>
		</div>
	</body>
</html>';

$pageBody = '';

}

$section = "articles";

if( isset( $_REQUEST[ "section" ] ) ) {	
	$section = $_REQUEST[ "section" ];
}

switch( $section ) {
	
	case "home" : {	
		if( isset( $_REQUEST[ "year" ] ) ) {
			$year = $_REQUEST[ "year" ];
					
			if( isset( $_REQUEST[ "month" ] ) ) {
				$month = $_REQUEST[ "month" ];
			}
		}
	}
	break;
	
	case "articles" : {
		$action = "list";
		
		if( isset( $_REQUEST[ "action" ] ) ) {
			$action = $_REQUEST[ "action" ];
		}
		
		switch( $action ) {
			
			case "list" : {
				$template = DOMTemplate::fromFile($index_page);
				$articles = getArticles();
				
				if( count( $articles ) > 0 ) {
					$template->remove(".no-posts");
					
					$limit = 5;
					
					if( count( $articles ) <= 5 ) {
						$limit = count( $articles );
					}
					
					for( $i = count($articles)-1; $i >= 0; $i-- ) {
						$article = new Article( $articles[ $i ] );
						$article_snippet = $template->repeat(".article");
						
						if( articleExists( 0, $articles[ $i ] ) ) {
							$article_snippet->setValue(".article-title",$article->getTitle());
							$article_snippet->setValue(".article-href@href","?section=articles&action=view&target={$articles[$i]}");
							$article_snippet->setValue("./@data-remove-flag","0");
							$article_snippet->remove(".article-excerpt");
							$article_snippet->remove(".no-posts");
							$article_snippet->remove(".inexistent-article-error");
						} else {
							$article_snippet->remove(".article-title");
							$article_snippet->remove(".article-excerpt");
							$article_snippet->remove(".no-posts");
						}
						
						$article_snippet->next();
					}
				} else {
					$template->remove(".article");
				}			
			}
			break;
						
			case "view" : {
				
				if( isset( $_REQUEST[ "target" ] ) ) {
					$template = DOMTemplate::fromFile($article_page);
					$article = new Article( $_REQUEST[ "target" ] );
					
					$template->setValue("#article-title", $article->getTitle());
					$template->setValue("#article-body",kses(Markdown($article->getBody()), $allowed ), true);
					$template->remove("#inexistent-article-error");
					$template->remove("#unspecified-article");
					

					if( count( $article -> getComments() ) > 0 ) {

						$comments = $article->getComments();
						for( $i = count($comments)-1; $i >= 0;  $i-- ) {
							$comment = new Comment( $comments[$i] );
							
							$comment_snippet = $template->repeat("div.comment");
							$comment_snippet->setValue('.meta//span[@data-comment="date"]', substr($comment->getDateCreated(), 0, 10));
							$comment_snippet->setValue('.meta//span[@data-comment="time"]', substr($comment->getDateCreated(), 11, 8));
							$comment_snippet->setValue('.meta//span[@data-comment="author"]', $comment->getAuthor());
							$comment_snippet->setValue('.comment-body', kses(Markdown($comment->getBody()), $allowed), true);
							$comment_snippet->setValue("@data-remove-flag","0");
							$comment_snippet->next();
						}
						
					}
					$template->setValue('.commentForm/form@action', '?section=comments&amp;action=new');
					$template->setValue('.commentForm/form/fieldset/#target@value', $article->getUniqueID());
				}
				else {
					$template->remove('#article');
					$template->remove("#inexistent-article-error");
				}
				
				if ( isset($_SESSION['comment_saved']) && ($_SESSION['comment_saved']==='ok') ) {
					$template->setValue('.comment-notification@style', 'display: block;');
					$template->remove('p@data-comment-notification="fail"');
				} else if ( isset($_SESSION['comment_saved']) && ($_SESSION['comment_saved']==='fail') ) {
					$template->setValue('.comment-notification@style', 'display: block;');
					$template->remove('p@data-comment-notification="ok"');
				}
				unset($_SESSION['comment_saved']);
				
				remove_comment_errors($template);
			
			}
			break;
		
		}
	
	} 
	break;
	
	case "comments" : {
		
		$action = "add";
		
		if( isset( $_REQUEST[ "action" ] ) ) {
			
			$action = $_REQUEST[ "action" ];
		
		}
		
		switch( $action ) {
			/** TODO
			 * Rework this to remove the various comment error checking and handling to a separate file
			 */
			case "add" :
			case "new" :
			default : {
				
				if( isset( $_POST[ "comment" ] ) && ($_POST["comment"] !== '') ) {
					
					if( isset( $_POST[ "name" ] ) && isset( $_POST[ "email" ] ) && isset( $_POST[ "target" ] ) && ( filter_var( $_POST[ "email" ], FILTER_VALIDATE_EMAIL ) ) ) {
						
						$comment = new Comment( "00000", $_POST[ "comment" ], $_POST[ "target" ], $_POST[ "name" ], $_POST[ "email" ] );
						
						if( $comment -> saveToDB() ) {
							$_SESSION['comment_saved'] = 'ok';
						}
						else {
							$_SESSION['comment_saved'] = 'fail';
						}
					
					}
					else {
						if ( (!isset($_POST['name'])) || ($_POST['name']==='') ) {
							$_SESSION['errors']['name']='empty';
						}
						
						if ( (!isset($_POST['email'])) || ($_POST['email']==='') ||  (!filter_var($_POST["email"],FILTER_VALIDATE_EMAIL)) ) {
							$_SESSION['errors']['email']='invalid';
						}
					}
					
					header("Location: ?section=articles&action=view&target={$_POST['target']}");
				}
				else {
						$_SESSION['errors']['comment']='empty';
						header("Location: ?section=articles&action=view&target={$_POST['target']}");
				}
			
			}
			break;
	
		}
	
	}
	break;	

}


					
$pageHeader .= '
					</div>
					<div class="mainColumn">';


$format = 'html';

if( isset( $_REQUEST[ "format" ] ) ) {
	
	$format = $_REQUEST[ "format" ];

}

switch( $format ) {
	
	case "html" : {
		
		$output = $pageHeader . $pageBody . $pageFooter;
	
	}
	break;
	
	case "ajax" : {
		
		$output = $pageBody;
	
	}
	break;
	
	case "json" : {
		
	
	}
	break;
	
	case "xml" : {}
	break;
	
	case "pdf" : {}
	break;
	 
}

//echo $output;
$template->remove('div@data-remove-flag="1"');
echo $template->html();

function remove_comment_errors(&$template) {
	if( !isset($_SESSION['errors']['name']) ){
		$template->remove('#comment-name-empty-error');
	}
	
	if( !isset($_SESSION['errors']['email']) ){
		$template->remove('#comment-email-empty-error');
	}
	
	if( !isset($_SESSION['errors']['comment']) ){
		$template->remove('#comment-empty-error');
	}
	
	unset($_SESSION['errors']);
}

?>
