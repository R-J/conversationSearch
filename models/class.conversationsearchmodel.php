<?php

class ConversationSearchModel extends Gdn_Pluggable { // Gdn_Pluggable {
    /**  @var Gdn_Database Database object. */
    public $Database;

    /** @var Gdn_SQLDriver Contains the sql driver for the object. */
    public $SQL;

    /**
     * Class constructor.
     *
     * @return  void.
     */
    public function __construct() {
        $this->Database = Gdn::database();
        $this->SQL = $this->Database->sql();

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
     * @param array $filter Array with filter columns and criteria. Possible
     *   values by now:
     *   integer ID: a ConversationID
     *   integer|false userID: a conversations participant user id, defaults to
     *     session user. Using "false" will skip user restriction.
     *
     * @return array Search result.
     */
    public function search($search, $offset = 0, $limit = 20, $filter = []) {
        // If a third party plugin wants to take influence on the source,
        // this would be a possibility to change the search string.
        // $this->EventArguments['Search'] = &$Search;
        // $this->fireEvent('Search');

        // If there are no searches then return an empty array.
        if (trim($search) == '') {
            return [];
        }

        $filter = array_change_key_case($filter);

        // Filter: by user
        $userID = val('userid', $filter, Gdn::session()->UserID);
        if ($userID !== false) {
            $this->SQL->where('uc.UserID', $userID);
        }

        // Filter: by id
        if (array_key_exists('id', $filter) && $filter['id'] > 0) {
            $this->SQL->where('c.ConversationID', $filter['id']);
        }

        // Build the search sql.
        $sql = $this->SQL
            ->distinct()
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
