package auth

// Credential holds S3 credential information
type Credential struct {
	ID             int64
	TenantID       int64
	AccessKey      string
	SecretKeyPlain string
	AllowedBucket  string
	AllowedPrefix  string
	IsActive       bool
}

// CredentialStore manages credentials
type CredentialStore struct {
	credentials map[string]*Credential
}

// NewCredentialStore creates a new credential store
func NewCredentialStore() *CredentialStore {
	return &CredentialStore{
		credentials: make(map[string]*Credential),
	}
}

// Get retrieves a credential by access key
func (cs *CredentialStore) Get(accessKey string) (*Credential, bool) {
	cred, ok := cs.credentials[accessKey]
	return cred, ok
}

// Set stores a credential
func (cs *CredentialStore) Set(cred *Credential) {
	cs.credentials[cred.AccessKey] = cred
}

// LoadAll replaces all credentials with the given list
func (cs *CredentialStore) LoadAll(creds []*Credential) {
	cs.credentials = make(map[string]*Credential)
	for _, cred := range creds {
		if cred != nil {
			cs.credentials[cred.AccessKey] = cred
		}
	}
}

// All returns all credentials
func (cs *CredentialStore) All() []*Credential {
	var result []*Credential
	for _, cred := range cs.credentials {
		result = append(result, cred)
	}
	return result
}

// Keys returns all access keys currently loaded (for debug logging)
func (cs *CredentialStore) Keys() []string {
	var keys []string
	for k := range cs.credentials {
		keys = append(keys, k)
	}
	return keys
}
