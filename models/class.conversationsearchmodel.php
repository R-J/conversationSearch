<?php

class ConversationSearchModel extends SearchModel {
    /**
     *
     *
     * @param $Search
     * @param int $Offset
     * @param int $Limit
     * @return array|null
     * @throws Exception
     */
    public function search($Search, $Offset = 0, $Limit = 20) {
        // If there are no searches then return an empty array.
        if (trim($Search) == '') {
            return array();
        }

        // Figure out the exact search mode.
        if ($this->ForceSearchMode) {
            $SearchMode = $this->ForceSearchMode;
        } else {
            $SearchMode = strtolower(c('Garden.Search.Mode', 'matchboolean'));
        }

        if ($SearchMode == 'matchboolean') {
            if (strpos($Search, '+') !== false || strpos($Search, '-') !== false) {
                $SearchMode = 'boolean';
            } else {
                $SearchMode = 'match';
            }
        } else {
            $this->_SearchMode = $SearchMode;
        }

        if ($ForceDatabaseEngine = c('Database.ForceStorageEngine')) {
            if (strcasecmp($ForceDatabaseEngine, 'myisam') != 0) {
                $SearchMode = 'like';
            }
        }

        if (strlen($Search) <= 4) {
            $SearchMode = 'like';
        }

        $this->_SearchMode = $SearchMode;

        if (count($this->_SearchSql) == 0) {
            return array();
        }

        // Perform the search by unioning all of the sql together.
        $Sql = $this->SQL
            ->select()
            ->from('_TBL_ s')
            ->orderBy('s.DateInserted', 'desc')
            ->limit($Limit, $Offset)
            ->getSelect();

        $Sql = str_replace($this->Database->DatabasePrefix.'_TBL_', "(\n".implode("\nunion all\n", $this->_SearchSql)."\n)", $Sql);

        if ($this->_SearchMode == 'like') {
            $Search = '%'.$Search.'%';
        }

        foreach ($this->_Parameters as $Key => $Value) {
            $this->_Parameters[$Key] = $Search;
        }

        $Parameters = $this->_Parameters;
        $this->reset();
        $this->SQL->reset();
        $Result = $this->Database->query($Sql, $Parameters)->resultArray();

        foreach ($Result as $Key => $Value) {
            if (isset($Value['Summary'])) {
                $Value['Summary'] = condense(Gdn_Format::to($Value['Summary'], $Value['Format']));
                $Result[$Key] = $Value;
            }

            switch ($Value['RecordType']) {
                case 'Discussion':
                    $Discussion = arrayTranslate($Value, array('PrimaryID' => 'DiscussionID', 'Title' => 'Name', 'CategoryID'));
                    $Result[$Key]['Url'] = discussionUrl($Discussion, 1);
                    break;
            }
        }

        return $Result;
    }
}
