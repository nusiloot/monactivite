<?php

namespace AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

/**
 * ActivityRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ActivityRepository extends EntityRepository
{

    public function findByDatesInterval($dateFrom, $dateTo, $nbDaysMax, $queryString = null) {
        $querySearchDQL = null;
        $querySearch = null;
        if($queryString) {
            $querySearch = $this->searchQueryToQueryDoctrine($queryString);
            $querySearchDQL = ' AND a IN('.$querySearch->getDQL().')';
        }

        $query = $this->getEntityManager()
                    ->createQuery('
                          SELECT DATE(a.executedAt) as date
                          FROM AppBundle:Activity a
                          WHERE a.executedAt >= :date_to AND a.executedAt <= :date_from'.$querySearchDQL.'
                          GROUP BY date
                          ORDER BY a.executedAt DESC
                      ')
                    ->setParameter('date_from', $dateFrom)
                    ->setParameter('date_to', $dateTo)
                    ->setMaxResults($nbDaysMax);

         if($querySearch) {
          foreach($querySearch->getParameters() as $p) {
              $query->setParameter($p->getName(), $p->getValue());
          }
        }

        $dates = $query->getScalarResult();

        if(!count($dates)) {

            return array();
        }

        $dateTo = $dates[count($dates) - 1]['date'];

        $query = $this->getEntityManager()
                    ->createQuery('
                          SELECT a, aa, at
                          FROM AppBundle:Activity a
                          LEFT JOIN a.attributes aa
                          LEFT JOIN a.tags at
                          WHERE a.executedAt >= :date_to AND a.executedAt <= :date_from'.$querySearchDQL.'
                          ORDER BY a.executedAt DESC
                      ')
                    ->setParameter('date_from', $dateFrom)
                    ->setParameter('date_to', $dateTo);

        if($querySearch) {
          foreach($querySearch->getParameters() as $p) {
              $query->setParameter($p->getName(), $p->getValue());
          }
        }

        return $query->getResult();
    }

    public function findByDate($date) {
        $dateTo = clone $date;
        $dateTo->modify("+1 day");

        return $this->findByDateInterval($date, $dateTo);
    }

    public function findByFilter($filter) {
        $querySearch = $this->searchQueryToQueryDoctrine($filter->getQuery());

        $query = $this->getEntityManager()
                    ->createQuery('
                          SELECT a
                          FROM AppBundle:Activity a
                          WHERE a NOT IN (SELECT asub FROM AppBundle:Activity asub JOIN asub.tags tsub WITH tsub = :tag)
                            AND a IN('.$querySearch->getDQL().')
                          ORDER BY a.executedAt DESC
                     ')
                    ->setParameter('tag', $filter->getTag());

        foreach($querySearch->getParameters() as $p) {
            $query->setParameter($p->getName(), $p->getValue());
        }

        return $query->getResult();
    }

    public function searchQueryToQueryDoctrine($searchQuery) {
        $terms = explode(" ", $searchQuery);
        $params = array();
        foreach($terms as $term) {
            $param = explode(":", $term);
            if(count($param) < 2) {
              $param[1] = $param[0];
              $param[0] = 'title';
            }
            array_push($params, $param);
        }

        $query = $this->getEntityManager()->createQueryBuilder()
                                 ->select('aq')
                                 ->from('AppBundle:Activity', 'aq');


        foreach($params as $key => $param) {
            $name = $param[0];
            $value = str_replace('*', '%', $param[1]);

            if($name == 'title' || $name == 'content') {
              $query->andwhere('aq.'.$name.' LIKE :q'.$key.'value')
                    ->setParameter('q'.$key.'value', $value);
            } elseif($name == 'tag') {
              $query->leftJoin('aq.tags', 'aqt'.$key)
                  ->andWhere('aqt'.$key.'.name LIKE :q'.$key.'value')
                  ->setParameter('q'.$key.'value', $value);
            } else {
                $query->leftJoin('aq.attributes', 'aqa'.$key)
                  ->andwhere('aqa'.$key.'.value LIKE :q'.$key.'value')
                  ->andWhere('aqa'.$key.'.name LIKE :q'.$key.'name')
                  ->setParameter('q'.$key.'name', $name)
                  ->setParameter('q'.$key.'value', $value);
            }
        }


        return $query;
    }
}
