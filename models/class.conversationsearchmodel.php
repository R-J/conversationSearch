<?php

class ConversationSearchModel extends SearchModel { // Gdn_Pluggable {
    /**  @var Gdn_Database Database object. */
    public $Database;

    /** @var Gdn_SQLDriver Contains the sql driver for the object. */
    public $SQL;

    /** @var boolean Restrict results to the conversations of one user */
    public $checkPermission = true;

    /** @var null|integer If checkPermission is true, restrict to this user. Session user will be used if this value isn't set */
    public $conversationUserID = null;

    /** @var boolean Enable searching in unread conversations. */
    public $searchUnread = false;

    /**
     * Class constructor.
     *
     * @param boolean $checkPermission Restrict results based on user.
     * @param null|integer $conversationUserID The user to use for restriction.
     *
     * @return  void.
     */
    public function __construct($checkPermission = true, $conversationUserID = null) {
        // $this->Database = Gdn::database();
        // $this->SQL = $this->Database->sql();

        $this->checkPermission = $checkPermission;
        if ($conversationUserID === null) {
            $conversationUserID = Gdn::session()->UserID;
        }
        $this->conversationUserID = $conversationUserID;

        parent::__construct();
    }

    /**
     * Performs a boolean search.
     *
     * Allows using "+" (AND) and "-" (NOT) in search.
     *
     * @param string $search The string to look up.
     * @param integer $offset The offset to start from (for pagination).
     * @param integer $limit The limit of results (for pagination).
     *
     * @return array Search result.
     */
    public function search($search, $offset = 0, $limit = 20) {
        // If a third party plugin wants to take influence on the source,
        // this would be a possibility to change the search string.
        // $this->EventArguments['Search'] = &$Search;
        // $this->fireEvent('Search');

        // If there are no searches then return an empty array.
        if (trim($search) == '') {
            return [];
        }

        // Optionally restrict results to one user.
        if ($this->checkPermission != false) {
            $this->SQL->where('uc.UserID', intval($this->conversationUserID));
        }

        // Allow searching in unread conversations.
        // Should be a) accessible based on role and b)
        if (
            c('conversationSearch.SearchUnread', false) == false ||
            $this->searchUnread == false
        ) {
            $this->SQL
                ->where('uc.DateLastViewed is not null')
                ->where('cm.MessageID <=', 'uc.LastMessageID', true, false);
        }

        // Build the search sql.
        $sql = $this->SQL
            ->select('cm.Body', "match (%s) against (:search1 in boolean mode)", 'Relevance')
            ->select('cm.MessageID as PrimaryID, c.Subject as Title, cm.Body as Summary, cm.Format')
            ->select("'messages/', cm.ConversationID, '#Message_', cm.MessageID", 'concat', 'Url')
            ->select('cm.DateInserted')
            ->select('cm.InsertUserID as UserID')
            ->select("'Message'", '', 'RecordType')
            ->select('u.Name, u.Photo, u.Email')
            ->from('ConversationMessage cm')
            ->join('Conversation c', 'cm.ConversationID = c.ConversationID', 'left outer')
            ->join('UserConversation uc', 'uc.ConversationID = cm.ConversationID', 'left outer')
            ->join('User u', 'cm.InsertUserID = u.UserID', 'left outer')
            ->where('uc.Deleted', 0) // Conversations which have been left cannot be searched
            ->where("match(cm.Body) against (:search2 in boolean mode)", null, false, false)
            ->orderBy('cm.DateInserted', 'desc')
            ->limit($limit, $offset)
            ->getSelect();

        // Third party plugins would be able to take influence on the query.
        // $this->EventArguments['Sql'] = &$sql;
        // $this->fireEvent('AfterBuildSearchQuery');

        // Add two named parameters for our search and execute the query.
        $this->SQL->namedParameter(':search1', false, $search);
        $this->SQL->namedParameter(':search2', false, $search);
        $result = $this->SQL->query($sql)->resultArray();

        // Condense Body To Summary.
        foreach ($result as $key => $value) {
            if (isset($value['Summary'])) {
                $result[$key]['Summary'] = condense(Gdn_Format::to($value['Summary'], $value['Format']));
            }
        }

        return $result;
    }
}
