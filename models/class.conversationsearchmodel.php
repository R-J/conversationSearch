<?php

class ConversationSearchModel extends SearchModel {
    /**
     *
     *
     * @param $search
     * @param int $offset
     * @param int $limit
     * @return array|null
     * @throws Exception
     */
    public function search($search, $offset = 0, $limit = 20) {
        // If there are no searches then return an empty array.
        if (trim($search) == '') { // || count($this->_SearchSql) == 0) {
            return [];
        }

        if (strlen($search) <= 4) {
            $searchMode = 'like';
        } else {
            // Figure out the exact search mode.
            if ($this->ForceSearchMode) {
                $searchMode = $this->ForceSearchMode;
            } else {
                $searchMode = strtolower(c('Garden.Search.Mode', 'matchboolean'));
            }

            if ($searchMode == 'matchboolean') {
                if (strpos($search, '+') !== false || strpos($search, '-') !== false) {
                    $searchMode = 'boolean';
                } else {
                    $searchMode = 'match';
                }
            }

            if ($ForceDatabaseEngine = c('Database.ForceStorageEngine')) {
                if (strcasecmp($ForceDatabaseEngine, 'myisam') != 0) {
                    $searchMode = 'like';
                }
            }
        }
        $this->_SearchMode = $searchMode;

        // Perform the search by unioning all of the sql together.
        $sql = Gdn::sql()
            ->select('cm.MessageID as PrimaryID, c.Subject as Title, cm.Body as Summary, cm.Format')
            ->select("'messages/', cm.ConversationID, '#Message_', cm.MessageID", "concat", 'Url')
            ->select('cm.DateInserted')
            ->select('cm.InsertUserID as UserID')
            ->select("'Message'", '', 'RecordType')
            ->from('ConversationMessage cm')
            ->join('Conversation c', 'c.ConversationID = cm.ConversationID')
            ->orderBy('cm.DateInserted', 'desc')
            ->limit($limit, $offset)
            ->getSelect();

        if ($this->_SearchMode == 'like') {
            $search = '%'.$search.'%';
        }
decho($search);
        foreach ($this->_Parameters as $key => $value) {
            $this->_Parameters[$key] = $search;
        }
        $parameters = $this->_Parameters;
        $this->reset();
        $this->SQL->reset();
decho($sql);
decho($parameters);

        $result = $this->Database->query($sql, $parameters)->resultArray();

        // Transform Body TO Summary.
        foreach ($result as $key => $value) {
            if (isset($value['Summary'])) {
                $result[$key]['Summary'] = condense(Gdn_Format::to($value['Summary'], $value['Format']));
            }
        }

        return $result;
    }
}
