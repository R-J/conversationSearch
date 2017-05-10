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
        $searchMode = strtolower(c('Conversation.Search.Mode', 'matchboolean'));

        // Perform the search by unioning all of the sql together.
        $sql = $this->SQL
            ->select('cm.Body', "match (%s) against(:search1 IN BOOLEAN MODE)", 'Relevance')
            ->select('cm.MessageID as PrimaryID, c.Subject as Title, cm.Body as Summary, cm.Format')
            ->select("'messages/', cm.ConversationID, '#Message_', cm.MessageID", 'concat', 'Url')
            ->select('cm.DateInserted')
            ->select('cm.InsertUserID as UserID')
            ->select("'Message'", '', 'RecordType')
            ->from('ConversationMessage cm')
            ->join('Conversation c', 'c.ConversationID = cm.ConversationID')
            ->where("match(cm.Body) against (:search2  IN BOOLEAN MODE)", null, false, false)
            ->orderBy('cm.DateInserted', 'desc')
            ->limit($limit, $offset)
            ->getSelect();

        $parameters = [':search1' => $search, ':search2' => $search];
        $this->SQL->namedParameters($parameters);
        $result = $this->SQL->query($sql)->resultArray();

        // Transform Body To Summary.
        foreach ($result as $key => $value) {
            if (isset($value['Summary'])) {
                $result[$key]['Summary'] = condense(Gdn_Format::to($value['Summary'], $value['Format']));
            }
        }

        return $result;
    }
}
