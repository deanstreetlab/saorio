<?php

class PostManager {

  protected $mysql;
  protected $user; //user of the post
  protected $numberOfPosts; //total number of posts by user
  protected $numberOfPages; //number of pages 
  protected const POSTS_PER_PAGE = 5; //number of posts to show per page in pagination


  public function __construct(string $user) {

    $this->mysql = MySQL::getInstance(); //database accessor instance

    //check username entered exists
    $usernameExists = $this->mysql->request($this->mysql->readMembersTableQuery, [":user" => $user]);
    if (!$usernameExists) {
      throw new Exception("username entered does not exist.");
    } else {
      $this->user = $user;
    }

    $this->numberOfPosts = $this->mysql->request($this->mysql->readPostNumberQuery, [":user" => $this->user])[0];
    $this->numberOfPages = ceil( $this->numberOfPosts / self::POSTS_PER_PAGE ); //total post number divided by posts per page, rounded up

  }

  /*
  get posts created by user
  @param int number, number of posts to retrieve
  @param int skip, number of posts to skip from 1 being most recent 
  @return array of post data [id, type, timestamp, text, [image rel paths], [image descriptions]]
  */
  public function getPosts(int $number = null, int $skip = null): ?array {

    //switch SQL query on input params
    if ( !is_null($skip) && !is_null($number) ) { //from a given number for a certain number of posts

      $posts = $this->mysql->request($this->mysql->readPostsQuery, [":user" => $this->user, ":offset" => $skip, ":count" => $number]);

    } elseif ( !is_null($skip) && is_null($number)  ) { //from a given number til the end

      $posts = $this->mysql->request($this->mysql->readPostsQuery, [":user" => $this->user, ":offset" => $skip, ":count" => 99]);

    } elseif ( is_null($skip) && !is_null($number) ) { //for a certain number of posts from most recent
      
      $posts = $this->mysql->request($this->mysql->readPostsQuery, [":user" => $this->user, ":offset" => 0, ":count" => $number]);
      
    } else { //get all posts

      $posts = $this->mysql->request($this->mysql->readPostsQuery, [":user" => $this->user, ":offset" => 0, ":count" => 99]);

    }

    $data = [];
    foreach ($posts as $post) {

      $type = $post["type"];
      $id = $post["id"];

      if ($type == 1) {

        $textPost = $this->mysql->request($this->mysql->readTextPostQuery, [":id" => $id]);
        array_push($data, [ "id" => $id, "type" => $type, "timestamp" => $textPost[0]["timestamp"], "text" => $textPost[0]["post"], "images" => null, "descriptions" => null ]);

      } elseif ($type == 2) {

        $imagePost = $this->mysql->request($this->mysql->readImagePostQuery, [":id" => $id]);
        $images = []; //rel path
        $descriptions = []; //caption
        foreach ($imagePost as $row) {
          array_push($images, UploadedPostImageFile::convertFileRelativePath($row["image"]));
          array_push($descriptions, $row["description"]);
        }
        array_push($data, [ "id" => $id, "type" => $type, "timestamp" => $imagePost[0]["timestamp"], "text" => $imagePost[0]["text"], "images" => $images, "descriptions" => $descriptions ]);

      } else {

        throw new Exception("invalid post type code.");

      }

    }

    return $data;

  }

  /*
  get post data for a certain paginated page
  @param page, pagination number
  @return array of post data [id, type, timestamp, text, [image rel paths], [image descriptions]]
  */
  public function getPage(int $page): ?array {

    if ($page < 1 || $page > $this->numberOfPages) {
      throw new Exception("input page is beyond available pagination.");
    }

    $skip = ($page - 1) * self::POSTS_PER_PAGE; //number of posts to skip before retrieving
    return $this->getPosts(self::POSTS_PER_PAGE, $skip); 

  }
  
  /*
  get image posts created by user
  @param int number, number of posts to retrieve
  @param int skip, number of posts to skip from 1 being most recent
  @return array whose elements are arrays of post data [id, timestamp, text, image rel path, image description]
  */
  public function getImagePosts(int $number = null, int $skip = null): ?array {

    //switch SQL query on input params
    if ( !is_null($skip) && !is_null($number) ) { //from a given number for a certain number of posts

      $rows = $this->mysql->request($this->mysql->readImagePostsQuery, [":user" => $this->user, ":offset" => $skip, ":count" => $number]);

    } elseif ( !is_null($skip) && is_null($number)  ) { //from a given number til the end

      $rows = $this->mysql->request($this->mysql->readImagePostsQuery, [":user" => $this->user, ":offset" => $skip, ":count" => 99]);

    } elseif ( is_null($skip) && !is_null($number) ) { //for a certain number of posts from most recent
      
      $rows = $this->mysql->request($this->mysql->readImagePostsQuery, [":user" => $this->user, ":offset" => 0, ":count" => $number]);
      
    } else { //get all posts

      $rows = $this->mysql->request($this->mysql->readImagePostsQuery, [":user" => $this->user, ":offset" => 0, ":count" => 99]);

    }

    $data = [];
    foreach ($rows as $row) {

      $relPath = UploadedPostImageFile::convertFileRelativePath($row["image"]); //abs to rel path
      array_push( $data, array_replace($row, ["image" => $relPath]) ); //append array after replacing abs path to rel path

    } 

    return $data;

  }

  /*
  get text posts created by user
  @param int number, number of posts to retrieve
  @param int skip, number of posts to skip from 1 being most recent
  @return array whose elements are arrays of post data [id, timestamp, post]
  */
  public function getTextPosts(int $number = null, int $skip = null): ?array {

    //switch SQL query on input params
    if ( !is_null($skip) && !is_null($number) ) { //from a given number for a certain number of posts

      return $this->mysql->request($this->mysql->readTextPostsQuery, [":user" => $this->user, ":offset" => $skip, ":count" => $number]);

    } elseif ( !is_null($skip) && is_null($number)  ) { //from a given number til the end

      return $this->mysql->request($this->mysql->readTextPostsQuery, [":user" => $this->user, ":offset" => $skip, ":count" => 99]);

    } elseif ( is_null($skip) && !is_null($number) ) { //for a certain number of posts from most recent
      
      return $this->mysql->request($this->mysql->readTextPostsQuery, [":user" => $this->user, ":offset" => 0, ":count" => $number]);
      
    } else { //get all posts

      return $this->mysql->request($this->mysql->readTextPostsQuery, [":user" => $this->user, ":offset" => 0, ":count" => 99]);

    }

  }

  /*
  get a number of images posted by user
  @param number of images to get
  @return relative paths of images, most recent first (timestamp desending order)
  */
  public function getPostedImages(int $number = null): ?array {

    $number = is_null($number) ? 99 : $number; //set number to all if isn't already set
    $ids = $this->mysql->request($this->mysql->readImagePostIdQuery, [":user" => $this->user, ":offset" => 0, ":count" => $number]);

    $paths = [];
    foreach($ids as $id) {

      $rows = $this->mysql->request($this->mysql->readImagePostImageQuery, [":id" => $id]);

      foreach($rows as $row) {
        array_push($paths, UploadedPostImageFile::convertFileRelativePath( $row["image"] ) );
      }

    }

    return $paths;

  }

  /*
  get the number of posts made by user
  @return number of posts
  */
  public function getNumberOfPosts(): int {

    return $this->numberOfPosts;

  }

  /*
  get the number of pages available for this user's posts according to pagination
  @return number of pages available
  */
  public function getNumberOfPages(): int {

    return ceil( $this->numberOfPosts / self::POSTS_PER_PAGE ); //total post number divided by posts per page, rounded up

  }

  /*
  get the number of image posts made by user
  @return number of images posts
  */
  public function getNumberOfImagePosts(): int {

    return $this->mysql->request($this->mysql->readImagePostNumberQuery, [":user" => $this->user]);

  }

  /*
  get number of images posted by user
  @return number of images
  */
  public function getNumberOfPostedImages(): int {

    return $this->mysql->request($this->mysql->readImagesNumber, [":user" => $this->user]);

  }

  /*
  convert image orientations into css configuration classes arbitrarily defined for image display
  @param array images, array of images each element being an absolute path
  @return array of css classes
  */
  static public function getImageCssClasses(array $images): ?array { 
  
  $orientations = []; //array of landscape vs portrait in matching order as images array
  foreach ($images as $image) {

    list($width, $height) = getimagesize($image); 
    $width >= $height ? array_push($orientations, "landscape") : array_push($orientations, "portrait");  //tag each img as either portrait or landscape

  }

  $numberOfImg = count($orientations);
  $numberOfPortrait = 0;
  $numberOfLandscape = 0;
  foreach ($orientations as $orientation) {
    $orientation == "portrait" ? $numberOfPortrait++ : $numberOfLandscape++;
  }

  $configs = [];
  switch ($numberOfImg) {

    case 1:
      $numberOfPortrait > 0 ? array_push($configs, "portrait-1-in-1-portrait") : array_push($configs, "landscape-1-in-1-landscape");
      break;

    case 2:
      if ($numberOfPortrait == 2) { //both portraits
        array_push($configs, "portrait-1-in-2-portrait", "portrait-2-in-2-portrait");
      } else if ($numberOfLandscape == 2) { //both landscape
        array_push($configs, "landscape-1-in-2-landscape", "landscape-2-in-2-landscape");
      } else { //1 landscape, 2 portrait
        array_push($configs, "landscape-1-in-2-mixed", "landscape-2-in-2-mixed"); 
      }
      break;

    case 3:
      if ($orientations[0] == "portrait") { //most recent image is a portrait 
        array_push($configs, "portrait-1-in-3-portrait", "portrait-2-in-3-portrait", "portrait-3-in-3-portrait");
      } else {
        array_push($configs, "landscape-1-in-3-landscape", "landscape-2-in-3-landscape", "landscape-3-in-3-landscape");
      }
        break;

    case 4:
      if ($orientations[0] == "portrait") { //most recent image is a portrait
        array_push($configs, "portrait-1-in-4-portrait", "portrait-2-in-4-portrait", "portrait-3-in-4-portrait", "portrait-4-in-4-portrait");
      } else {
        array_push($configs, "landscape-1-in-4-landscape", "landscape-2-in-4-landscape", "landscape-3-in-4-landscape", "landscape-4-in-4-landscape");
      } 
      break;

    case 5:
      if ($orientations[0] == "portrait") { //most recent image is a portrait
        array_push($configs, "portrait-1-in-5-portrait", "portrait-2-in-5-portrait", "portrait-3-in-5-portrait", "portrait-4-in-5-portrait", "portrait-5-in-5-portrait");
      } else {
        array_push($configs, "landscape-1-in-5-landscape", "landscape-2-in-5-landscape", "landscape-3-in-5-landscape", "landscape-4-in-5-landscape", "landscape-5-in-5-landscape");
      }
      break;

    default: 
      throw new Exception("number of images exceeed 5 while configurations are defined up to 5.");    

  }

  return $configs;

}


} //end class

?>