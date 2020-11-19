CREATE SEQUENCE refile_request_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE refile_request_id_seq OWNER TO refile;

CREATE TABLE refile_request (
    id integer DEFAULT nextval('refile_request_id_seq'::regclass) NOT NULL,
    job_id text,
    item_barcode text,
    updated_date text,
    created_date text,
    success boolean DEFAULT false
);

ALTER TABLE refile_request OWNER TO refile;

ALTER TABLE ONLY refile_request
    ADD CONSTRAINT refile_request_pkey PRIMARY KEY (id);

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM refile;
GRANT ALL ON SCHEMA public TO refile;
GRANT ALL ON SCHEMA public TO PUBLIC;

REVOKE ALL ON SEQUENCE refile_request_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE refile_request_id_seq FROM refile;
GRANT ALL ON SEQUENCE refile_request_id_seq TO refile;
GRANT SELECT,USAGE ON SEQUENCE refile_request_id_seq TO refile;

REVOKE ALL ON TABLE refile_request FROM PUBLIC;
REVOKE ALL ON TABLE refile_request FROM refile;
GRANT ALL ON TABLE refile_request TO refile;

ALTER TABLE refile_request ADD COLUMN af_message text;
ALTER TABLE refile_request ADD COLUMN sip2_response text;

CREATE INDEX idx_created_date ON refile_request(created_date);
