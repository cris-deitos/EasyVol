<?php
namespace EasyVol\Utils;

/**
 * Training Course Types Helper
 * 
 * Provides a centralized list of Italian Civil Protection training course types
 * Based on Sistema di Supporto alla Protezione Civile (SSPC) classification
 */
class TrainingCourseTypes {
    
    /**
     * Get all available course types
     * 
     * @return array Associative array with course codes as keys and full names as values
     */
    public static function getAll() {
        return [
            // Corsi Base
            'A0' => 'A0 Corso informativo rivolto alla cittadinanza',
            'A1' => 'A1 Corso base per volontari operativi di Protezione Civile',
            
            // Corsi A2 - Specializzazione
            'A2-01' => 'A2-01 ATTIVITA\' LOGISTICO GESTIONALI',
            'A2-02' => 'A2-02 OPERATORE SEGRETERIA',
            'A2-03' => 'A2-03 CUCINA IN EMERGENZA',
            'A2-04' => 'A2-04 RADIOCOMUNICAZIONI E PROCESSO COMUNICATIVO IN PROTEZIONE CIVILE',
            'A2-05' => 'A2-05 IDROGEOLOGICO: ALLUVIONE',
            'A2-06' => 'A2-06 IDROGEOLOGICO: FRANE',
            'A2-07' => 'A2-07 IDROGEOLOGICO: SISTEMI DI ALTO POMPAGGIO',
            'A2-08' => 'A2-08 USO MOTOSEGA E DECESPUGLIATORE',
            'A2-09' => 'A2-09 SICUREZZA IN PROTEZIONE CIVILE: D. Lgs. 81/08',
            'A2-10' => 'A2-10 TOPOGRAFIA E GPS',
            'A2-11' => 'A2-11 RICERCA DISPERSI',
            'A2-12' => 'A2-12 OPERATORE NATANTE IN EMERGENZA DI PROTEZIONE CIVILE',
            'A2-13' => 'A2-13 INTERVENTI ZOOTECNICI IN EMERGENZA DI PROTEZIONE CIVILE',
            'A2-14' => 'A2-14 PIANO DI PROTEZIONE CIVILE: DIVULGAZIONE E INFORMAZIONE',
            'A2-15' => 'A2-15 QUADERNI DI PRESIDIO',
            'A2-16' => 'A2-16 EVENTI A RILEVANTE IMPATTO LOCALE',
            'A2-17' => 'A2-17 SCUOLA I° CICLO DELL\'ISTRUZIONE',
            'A2-18' => 'A2-18 SCUOLA SECONDARIA SUPERIORE',
            
            // Corsi A3 - Coordinamento
            'A3-01' => 'A3-01 CAPO SQUADRA',
            'A3-02' => 'A3-02 COORDINATORE TERRITORIALE DEL VOLONTARIATO',
            'A3-03' => 'A3-03 VICE COORDINATORE DI SEGRETERIA E SUPPORTO ALLA SALA OPERATIVA',
            'A3-04' => 'A3-04 PRESIDENTE ASSOCIAZIONE e/o COORD. GR. COMUNALE/INTERCOM.',
            'A3-05' => 'A3-05 COMPONENTI CCV (eletti)',
            'A3-06' => 'A3-06 SUPPORTO ALLA PIANIFICAZIONE',
            
            // Corsi A4 - Alta Specializzazione
            'A4-01' => 'A4-01 **SOMMOZZATORI di Protezione civile: Operatore tecnico assistenza sommozzatori PC 1°livello "Attività subacquee e soccorso nautico"',
            'A4-02' => 'A4-02 **SOMMOZZATORI di protezione civile Alta specializzazione "Attività subacquee"',
            'A4-03' => 'A4-03 ATTIVITA\' OPERATORI CINOFILI',
            'A4-04' => 'A4-04 ATTIVITA\' OPERATORI EQUESTRI',
            'A4-05' => 'A4-05 CATTURA IMENOTTERI E BONIFICA',
            'A4-06' => 'A4-06 T.S.A. - Tecniche Speleo Alpinistiche',
            'A4-07' => 'A4-07 S.R.T. - Swiftwater Rescue Technician',
            'A4-08' => 'A4-08 PATENTE PER OPERATORE RADIO AMATORIALE',
            'A4-09' => 'A4-09 OPERATORE GRU SU AUTO-CARRO',
            'A4-10' => 'A4-10 OPERATORE MULETTO',
            'A4-11' => 'A4-11 OPERATORE PER PIATTAFORME DI LAVORO ELEVABILI (PLE)',
            'A4-12' => 'A4-12 OPERATORE ESCAVATORE',
            'A4-13' => 'A4-13 OPERATORE TRATTORE',
            'A4-14' => 'A4-14 OPERATORE DRONI',
            'A4-15' => 'A4-15 HACCP',
            
            // Corsi A5 - AIB (Antincendio Boschivo)
            'A5-01' => 'A5-01 A.I.B. di 1° LIVELLO',
            'A5-02' => 'A5-02 A.I.B. AGGIORNAMENTI',
            'A5-03' => 'A5-03 CAPOSQUADRA A.I.B.',
            'A5-04' => 'A5-04 D.O.S. (in gestione direttamente a RL)',
            
            // Altro
            'Altro' => 'Altro da specificare'
        ];
    }
    
    /**
     * Get grouped course types for dropdown display
     * 
     * @return array Array of groups with label and options
     */
    public static function getGrouped() {
        return [
            'Corsi Base' => [
                'A0' => 'A0 - Corso informativo',
                'A1' => 'A1 - Corso base'
            ],
            'Corsi A2 - Specializzazione' => [
                'A2-01' => 'A2-01 - Logistico gestionali',
                'A2-02' => 'A2-02 - Segreteria',
                'A2-03' => 'A2-03 - Cucina emergenza',
                'A2-04' => 'A2-04 - Radiocomunicazioni',
                'A2-05' => 'A2-05 - Alluvione',
                'A2-06' => 'A2-06 - Frane',
                'A2-07' => 'A2-07 - Alto pompaggio',
                'A2-08' => 'A2-08 - Motosega',
                'A2-09' => 'A2-09 - Sicurezza D.Lgs 81/08',
                'A2-10' => 'A2-10 - Topografia GPS',
                'A2-11' => 'A2-11 - Ricerca dispersi',
                'A2-12' => 'A2-12 - Natante emergenza',
                'A2-13' => 'A2-13 - Interventi zootecnici',
                'A2-14' => 'A2-14 - Piano PC',
                'A2-15' => 'A2-15 - Quaderni presidio',
                'A2-16' => 'A2-16 - Eventi rilevanti',
                'A2-17' => 'A2-17 - Scuola I° ciclo',
                'A2-18' => 'A2-18 - Scuola secondaria'
            ],
            'Corsi A3 - Coordinamento' => [
                'A3-01' => 'A3-01 - Capo squadra',
                'A3-02' => 'A3-02 - Coordinatore territoriale',
                'A3-03' => 'A3-03 - Vice coordinatore',
                'A3-04' => 'A3-04 - Presidente',
                'A3-05' => 'A3-05 - CCV',
                'A3-06' => 'A3-06 - Pianificazione'
            ],
            'Corsi A4 - Alta Specializzazione' => [
                'A4-01' => 'A4-01 - Sommozzatori 1°liv',
                'A4-02' => 'A4-02 - Sommozzatori avanz',
                'A4-03' => 'A4-03 - Cinofili',
                'A4-04' => 'A4-04 - Equestri',
                'A4-05' => 'A4-05 - Imenotteri',
                'A4-06' => 'A4-06 - TSA',
                'A4-07' => 'A4-07 - SRT',
                'A4-08' => 'A4-08 - Radio amatoriale',
                'A4-09' => 'A4-09 - Gru',
                'A4-10' => 'A4-10 - Muletto',
                'A4-11' => 'A4-11 - PLE',
                'A4-12' => 'A4-12 - Escavatore',
                'A4-13' => 'A4-13 - Trattore',
                'A4-14' => 'A4-14 - Droni',
                'A4-15' => 'A4-15 - HACCP'
            ],
            'Corsi A5 - AIB' => [
                'A5-01' => 'A5-01 - AIB 1° livello',
                'A5-02' => 'A5-02 - AIB aggiornamenti',
                'A5-03' => 'A5-03 - Caposquadra AIB',
                'A5-04' => 'A5-04 - DOS'
            ]
        ];
    }
    
    /**
     * Get course name by code
     * 
     * @param string $code Course code
     * @return string|null Full course name or null if not found
     */
    public static function getName($code) {
        $types = self::getAll();
        return $types[$code] ?? null;
    }
}
