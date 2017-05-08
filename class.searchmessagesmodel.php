<?php

class SearchMessagesModel extends SearchModel {
    public function search($search, $offset = 0, $limit) {
decho(__LINE__);
        // If there are no searches then return an empty array.
        if (trim($search) == '') {
            return [];
        }

        if (!isset($limit)) {
            $limit = c('Garden.Search.PerPage', 20);
        }

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
        } else {
            parent::_SearchMode = $searchMode;
        }

        if ($forceDatabaseEngine = c('Database.ForceStorageEngine')) {
            if (strcasecmp($forceDatabaseEngine, 'myisam') != 0) {
                $SearchMode = 'like';
            }
        }

        if (strlen($search) <= 4) {
            $searchMode = 'like';
        }

        parent::_SearchMode = $searchMode;

        $this->EventArguments['Search'] = $search;
        $this->fireEvent('SearchMessages');

        if (count(parent::_SearchSql) == 0) {
            return [];
        }

        // Perform the search by unioning all of the sql together.
        $Sql = $this->SQL
            ->select()
            ->from('_TBL_ s')
            ->orderBy('s.DateInserted', 'desc')
            ->limit($Limit, $Offset)
            ->getSelect();

        $sql = str_replace($this->Database->DatabasePrefix.'_TBL_', "(\n".implode("\nunion all\n", $this->_SearchSql)."\n)", $Sql);

        $this->EventArguments['Sql'] = &$sql;
        $this->fireEvent('AfterBuildSearchQuery');

        if (parent::_SearchMode == 'like') {
            $search = '%'.$search.'%';
        }

        foreach (parent::_Parameters as $key => $value) {
            parent::_Parameters[$key] = $search;
        }

        $parameters = parent::_Parameters;
        $this->reset();
        $this->SQL->reset();
        $result = $this->Database->query($sql, $parameters)->resultArray();

        foreach ($result as $key => $value) {
            if (isset($value['Summary'])) {
                $value['Summary'] = condense(Gdn_Format::to($value['Summary'], $value['Format']));
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
