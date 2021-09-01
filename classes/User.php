<?php 


class User {
 
  //variables declaration
  protected $user; //username
  protected $profile; //Profile object, full one
  protected $friends = []; //his friends list, array of user objects
  protected $numberOfFriends; //number of his friends, length of the friends array
  protected $chatWith = []; //list of users he has had a conversation with, array of user objects
  
  /*
  constructor
  @param $user username
  */
  public function __construct(string $username) {

    $this->user = $username; 
    $this->profile = new FullProfile($this->user); //instantiate a Profile obj  

  }

  /*
  getter of this User's username
  */
  public function getUsername(): string {

    return $this->user;
    
  }

  /*
  function to get this User's friends list
  @return array of User objects
  */
  public function getFriends(): array {
    
    global $getAllFriendsQuery;

    $resultset = queryDB($getAllFriendsQuery, [":user" => $this->user]); //data of friends' usernames

    foreach ($resultset as $row) {

      $friend = new User($row["user"]); //instantiate a User obj for each friend
      array_push($this->friends, $friend); //append to array of friend objs

    }

    return $this->friends;

  }
  
  /*
  getter for this user's friends number
  @return his number of friends
  */
  public function getNumberOfFriends(): int {

    global $getAllFriendsQuery;

    $resultset = queryDB($getAllFriendsQuery, [":user" => $this->user]); //data of friends' usernames

    $this->numberOfFriends = count($resultset);

    return $this->numberOfFriends;

  }

  /*
  function to get the existing defined relationship between this user and another user
  @param the other user's username
  @return defined relationship code, 0 for stranger, 1 for existing friend, 2 for friend request sent, 3 for friend request received
  */
  public function getRelationshipWith (string $thatuser): int {

    $friendship = new Friendship($this->user, $thatuser);

    return $friendship->getFriendship();

  }

  /*
  function to retrieve this user's profile object
  @param $basic = true/false to indicate whether a BasicProfile obj or a FullProfile obj to retrieve
  @return either a FullProfile or BasicProfile obj
  */
  public function getProfile(bool $basicProfile = true): BasicProfile {

    return $basicProfile ? new BasicProfile($this->user) : $this->profile; //return his Profile obj, or create a new Basic Profile if basic if flagged

  }

  /*
  function to retrieve all users that this user has had a conversation with
  @return array of User objects
  */
  public function getChatWith(): array {

    global $getChattedWithQuery;

    $resultset = queryDB($getChattedWithQuery, [":me" => $this->user]);

    foreach ($resultset as $row) {

      $chatWith = new User($row["chatWith"]);
      array_push($this->chatWith, $chatWith);
    }

    return $this->chatWith;

  }

  /*
  function get this user's conversation with another particular user
  @param $chatWith the username of the other person of the conversation to retrieve
  @return Conversation object
  */
  public function getConversationWith(string $chatWith): Conversation {

    return new Conversation($this->user, $chatWith);

  }

  








} //end class

?>