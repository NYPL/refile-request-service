CREATE SEQUENCE refile_request_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE refile_request (
    id integer DEFAULT nextval('refile_request_id_seq'::regclass) NOT NULL,
    job_id text,
    item_barcode text,
    updated_date text,
    created_date text,
    success boolean DEFAULT false
);

ALTER TABLE ONLY refile_request
    ADD CONSTRAINT refile_request_pkey PRIMARY KEY (id);

ALTER TABLE refile_request ADD COLUMN af_message text;
ALTER TABLE refile_request ADD COLUMN sip2_response text;

CREATE INDEX idx_created_date ON refile_request(created_date);
