import sys, json
sys.path.insert(0,'/home/user/realestate/qa/scripts')
from sec_lib import *
GETS = ['/admin','/admin/activity','/admin/ai','/admin/cms/blueprints','/admin/cms/blueprints/create',
 '/admin/cms/collections/journal/entries','/admin/cms/collections/pages/entries','/admin/cms/collections/properties/entries',
 '/admin/cms/collections/agents/entries','/admin/cms/collections/journal/entries/export','/admin/cms/collections/pages/entries/export','/admin/cms/collections/properties/entries/export',
 '/admin/cms/collections/pages/listing','/admin/cms/comments','/admin/cms/entries','/admin/cms/entries/35/edit','/admin/cms/entries/30/edit','/admin/cms/entries/36/edit','/admin/cms/entries/10/edit','/admin/cms/entries/10/edit?locale=id','/admin/cms/entries/1/edit',
 '/admin/cms/entry-picker?collection=agents','/admin/cms/entry-picker?collections[]=agents','/admin/cms/entry-picker?q=a','/admin/cms/term-picker?taxonomy=topics','/admin/cms/term-picker?taxonomy=property_types',
 '/admin/cms/fieldsets','/admin/cms/forms/contact/submissions','/admin/cms/forms/contact/submissions/export','/admin/cms/globals','/admin/cms/globals/company','/admin/cms/menus','/admin/cms/menus/main',
 '/admin/cms/submissions','/admin/cms/taxonomies/topics/terms','/admin/cms/taxonomies/locations/terms','/admin/cms/terms','/admin/cms/terms/18/edit','/admin/cms/terms/22/edit',
 '/admin/collections','/admin/collections/export','/admin/docs','/admin/docs/getting-started','/admin/forms','/admin/global-sets','/admin/guide','/admin/media','/admin/media/picker','/admin/media/2/usages',
 '/admin/menus','/admin/preferences/x','/admin/redirects','/admin/redirects/export','/admin/resources/users/options/roles','/admin/resources/collections/options/taxonomies','/admin/roles','/admin/roles/3/edit','/admin/settings','/admin/settings/site','/admin/settings/mail','/admin/settings/security','/admin/settings/branding','/admin/settings/code','/admin/taxonomies','/admin/users','/admin/users/1/edit','/admin/users/2/edit','/admin/users/export','/admin/roles/export','/admin/kept-edits/1']
def main():
    users = sys.argv[1:] or ['budi','citra','dewi','eko','fajar']
    res={}
    for u in users:
        s=login(u)
        print('##',u,'login',s.login_status,s.login_resp.headers.get('location'), (s.login_resp.text[:200] if s.login_status!=302 else ''))
        for g in GETS:
            r=s.get(BASE+g, allow_redirects=False, headers={'Accept':'text/html'})
            loc=r.headers.get('location','')
            res.setdefault(g,{})[u]=f"{r.status_code}{'->'+loc.replace(BASE,'') if loc else ''}"
    for g,v in res.items():
        print(g.ljust(62),' '.join(f"{u}:{v[u]}" for u in users))
if __name__=='__main__': main()
