<?php
//concreate class for profile image uploads

class UploadedProfileImageFile extends UploadedImageFile {

    //new static variables
    protected static $uploadedDir = UPLOAD_DIR;
    const MAX_WIDTH = 300; //max width allowed in px
    const MAX_HEIGHT = 300; //max height allowed in px
    //new instance variables
    protected $filename; //filename to save to, without ext
    protected $mysql; //object for mysql database access

    /*
    @Override
    constructor, inherited from super
    instantiate this class instance variables
    */
    public function __construct($uploadedFile) {

        parent::__construct($uploadedFile); //super constructor 
        $this->filename = $_SESSION["user"] . "-" . filemtime($this->tempFilePath); //<username>-<timestamp> as filename, where timestamp is unix upload time
        $this->mysql = MySQL::getinstance();

    }

    /*
    @Override
    function to upload file after related procedures 
    operations include setting and moving to permanent path, checking file properties, image processing, database persistance, optionally deleting existing profile photo 
    @param $deleteExisting true to remove old profile img file or false to leave it untouched
    */
    public function upload(bool $deleteExisting = false): bool {

        $success = false;

        if ( $this->setPermFilePath(self::$uploadedDir, $this->filename) && $this->checkFile() ) { //call setter to set $permFilePath, check file properties

            if ( $this->move() ) { //if file is moved from temp to perm

                if ($this->process() ) { //if file is successfully processed

                    if ($deleteExisting) { //if existing file should be removed, get the existing path, delete it after successful persistance
                        
                        $oldFilePath = $this->mysql->request($this->mysql->readBasicProfileQuery, [":user" => $_SESSION["user"]])[0]["profilePictureURL"];
                        
                        if( $this->persist() ) { //if file is successfully persisted

                            $success = true;
                            if (!unlink($oldFilePath)) {
                                error_log("Failed to delete a profile photo: " . $oldFilePath);
                            }
                        
                        } 
                        
                    } else { //if no need to remove existing file

                        $success = $this->persist(); 

                    }

                }

            }

        }

        return $success;

    }

    /*
    @Override
    function to persist profile picture data to database
    */
    protected function persist(): bool {

        $params = [":url" => "$this->permFilePath", ":mime" => "$this->mime", ":user" => $_SESSION["user"] ];

        try {

            $this->mysql->request($this->mysql->updateProfilePictureQuery, $params);
            $success = true;

        } catch (Exception $ex) {

            $success = false;
            array_push($this->errorCodes, -1);
            error_log("Cannot persist a profile picture upload: " . $ex->getMessage());
            
        }

        return $success;
        
    }

    /*
    function to resize the oversized image to a square of MAX_WIDTH/MAX_HEIGHT, using PHP GD functions
    it first resizes the image's smaller dimension to MAX, maintaining aspect ratio
    it then fits the larger dimension to MAX using its centre, cropping away its two sides
    it overwrites the original image to file and then destroys all image resources in memory
    */
    protected function process(): bool {

        //use the MIME-based functions to create an image resource handle of this file
        switch ($this->mime) {

            case "image/jpeg":
                $photo_src = imagecreatefromjpeg($this->permFilePath);
                break;
            case "image/png":
                $photo_src = imagecreatefrompng($this->permFilePath);
                break;
            case "image/gif":
                $photo_src = imagecreatefromgif($this->permFilePath);
                break;
            case "image/webp":
                $photo_src = imagecreatefromwebp($this->permFilePath);
                break;
            default:
                array_push($this->errorCodes, 2);
                return false;

        }        

        //scale the photo, smaller dimension side to MAX
        if ($this->height > $this->width) { //portrait photo

            $scaledHeight = $this->height * ( self::MAX_WIDTH / $this->width );
            $photo_scaled = imagescale($photo_src, self::MAX_WIDTH, $scaledHeight, IMG_BICUBIC);
            $this->width = self::MAX_WIDTH;
            $this->height = $scaledHeight;
            imagedestroy($photo_src); 

        } else { //landscape photo, or already square

            $scaledWidth = $this->width * ( self::MAX_HEIGHT / $this->height );
            $photo_scaled = imagescale($photo_src, $scaledWidth, self::MAX_HEIGHT, IMG_BICUBIC);
            $this->width = $scaledWidth;
            $this->height = self::MAX_HEIGHT;
            imagedestroy($photo_src); 

        }
        //check scaling success
        if (!$photo_scaled) { //if scaling fails, it returns false
            array_push($this->errorCodes, -1);
            error_log("Error occurred in scaling a profile photo.");
            return false;
        }

        //crop the over-sized dimension to MAX (square needs no cropping)
        if ($this->height > $this->width) { //portrait, so crop height

            //calc crop dimension
            $fat = ( $this->height - self::MAX_HEIGHT ) / 2;
            $trimStart = 0 + $fat;
            
            //crop
            $dimen = ["x" => 0, "y" => $trimStart, "width" => $this->width, "height" => self::MAX_HEIGHT] ;
            $photo_cropped = imagecrop($photo_scaled, $dimen);

            imagedestroy($photo_scaled);

        } elseif ($this->height < $this->width) { //landscape, so crop width

            //calc crop dimension
            $fat = ( $this->width - self::MAX_WIDTH ) / 2;
            $trimStart = 0 + $fat;

            //crop
            $dimen = ["x" => $trimStart, "y" => 0, "width" => self::MAX_WIDTH, "height" => $this->height];
            $photo_cropped = imagecrop($photo_scaled, $dimen);

            imagedestroy($photo_scaled);

        }
        //check cropping success
        if (!$photo_cropped) { //if cropping fails, it returns false
            array_push($this->errorCodes, -1);
            error_log("Error occurred in cropping a profile photo.");
            return false;
        }

        //saving file
        //use the MIME-based functions to save the image resource to file
        switch ($this->mime) {

            case "image/jpeg":
                $success = imagejpeg($photo_cropped, $this->permFilePath, 100);
                imagedestroy($photo_cropped);
                break;
            case "image/png":
                $success = imagepng($photo_cropped, $this->permFilePath, 9);
                imagedestroy($photo_cropped);
                break;
            case "image/gif":
                $success = imagegif($photo_cropped, $this->permFilePath);
                imagedestroy($photo_cropped);
                break;
            case "image/webp":
                $success = imagewebp($photo_cropped, $this->permFilePath, 100);
                imagedestroy($photo_cropped);
                break;
            default:
                array_push($this->errorCodes, 2);
                return false;

        }
        //check save success
        if (!$success) {
            array_push($this->errorCodes, -1);
            error_log("Error occurred in saving a processed photo to file.");
        }

        return $success;

    } //end function

    /*
    function to delete the current profile picture file of this user on server
    */
    public function deletePhoto(): bool {

        $existingFilePath = $this->mysql->request($this->mysql->readBasicProfileQuery, [":user" => $_SESSION["user"]])[0]["profilePictureURL"];
        return unlink($existingFilePath);

    }



} //end class


?>