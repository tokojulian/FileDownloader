<?php

namespace FileD\FileBundle\Controller;


use Symfony\Component\HttpFoundation\RedirectResponse;

use FileD\FileBundle\Form\Type\ShareFileType;

use FileD\FileBundle\Form\Data\Share;

use Symfony\Component\HttpFoundation\Response;

use FileD\FileBundle\Factory\FileFactory;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use FileD\FileBundle\Entity\File;
use FileD\FileBundle\Form\FileType;
use FileD\FileBundle\Manager\UploadManager;
use FileD\ParamBundle\Manager\ParameterManager;

/**
 * File controller.
 * @author epidoux <eric.pidoux@gmail.com>
 * @version 1.0
 */
class FileController extends Controller
{

	/**
	 * Constant path of the upload dir
	 * @var string
	 */
	const UPLOAD_DIR = "/../../../../web/data/uploads/files/";
	
	/**
	 * Constant path of the download dir for zip
	 * @var string
	 */
	const DOWNLOAD_DIR = "/../../../../web/data/downloads/zip";
   
    /**
     * Displays the view to upload file (modal box)
     *
     * @Route("/new", name="file_new")
     * @Template()
     */
    public function newAction($file_id)
    {
		$this->get('logger')->info('[FileController] Displaying view for upload file with id '.$file_id);
        return array(
            'file_id' => $file_id
        );
    }

    /**
     * Creates a new File entity.
     *
     * @Route("/create", name="file_create")
     * @Method("POST")
     * @Template("FileDFileBundle:File:new.html.twig")
     */
    public function createAction(Request $request)
    {
        $entity  = new File();
        $form = $this->createForm(new FileType(), $entity);
        $form->bind($request);
		
        if ($form->isValid()) {
        	try{
	            $em = $this->getDoctrine()->getManager();
	            $em->persist($entity);
	            $em->flush();
			
	            $this->get('logger')->info('[FileController] Create new '.$entity);
            	//add flash msg to user
	            $this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.file.new'));
        	}
        	catch(\Exception $e){

        		//add flash msg to user
        		$this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.new'));
        		
        		$this->get('logger')->err('[FileController] Error while updating  '.$entity);
        	}
            return $this->redirect($this->generateUrl('file_show', array('id' => $entity->getId())));
        }

        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
        );
    }

    /**
     * Edits an existing File entity.
     *
     * @Route("/{id}/update", name="file_update")
     * @Method("POST")
     * @Template("FileDFileBundle:File:edit.html.twig")
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('FileDFileBundle:File')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find File entity.');
        }
	
        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createForm(new FileType(), $entity);
        $editForm->bind($request);

        if ($editForm->isValid()) {
        	try{
	            $em->persist($entity);
	            $em->flush();

	            //add flash msg to user
	            $this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.file.edit'));
	            $this->get('logger')->info('[FileController] Updating '.$entity);
	            
            }
            catch(\Exception $e){
        		//add flash msg to user
        		$this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.edit'));
            
            	$this->get('logger')->err('[FileController] Error while updating  '.$entity);
            }
            return $this->redirect($this->generateUrl('file_edit', array('id' => $id)));
        }

        return array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        );
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm()
        ;
    }
    
    /**
     * Add files to be uploaded through multiupload plugin
     * @param $_FILES['files']
     * @param $_POST['parent']
     */
    public function addfileAction(){

    	$uploaded_file = new \stdClass();
    		
    		$file = $this->container->get('filed_file.file')->create();
    		//upload the file
    		if ($_FILES['files']['tmp_name'][0]!="" && $_FILES['files']['error'][0] == 0) {
				try{
			    	$user= $this->get('security.context')->getToken()->getUser();
	    			if(array_key_exists('parent', $_POST) && $_POST['parent']!=0){
	    				$parent = $this->container->get('filed_file.file')->load($_POST['parent']);
	    				if($parent!=null){
	    					$file->setParent($parent);
	    					//add parent author to child author
	    					$file->setAuthor($parent->getAuthor());
	    				}
	    				else throw new \Exception("Can't find parent with id "+$_POST['parent']);
	
	    				$path = $parent->getLink()."/".$_FILES['files']['name'][0];
	    			}
	    			else{
	    				$path =  __DIR__.FileController::UPLOAD_DIR.$_FILES['files']['name'][0];

	    				$file->setAuthor($user);
	    			}
	    			 
	    			$resu =move_uploaded_file($_FILES['files']['tmp_name'][0], $path);
	    			 
			    	//Create the entity and the response file
			    	$file->setName($_FILES['files']['name'][0]);
			    	$file->setSize($_FILES['files']['size'][0]);
			    	$file->setMime($_FILES['files']['type'][0]);
			    	$file->setDateCreation(new \DateTime());
			    	$file->addUsersShare(array($user));
			    	//Add administrator users

			    	$this->container->get('filed_file.file')->update($file);
			    	$admins = $this->container->get('filed_user.user')->findAdministrators();
			    	foreach($admins as $admin)
			    	{
			    		//Shared the file to all administrators and escape the current user in case of it's an admin
			    		if($admin->getId()!=$user->getId()){
			    			$file->addUsersShare(array($admin));
					    	$admin->addFiles(array($file));
					    	$this->container->get('fos_user.user_manager')->updateUser($admin);
			    		}
			    	}
			    	
			    	$file->setLink($path);
			    	$file->setExternal(false);
			    	$this->container->get('filed_file.file')->update($file);

			    	$file->setHash(md5($this->container->getParameter("app_hash_passphrase").$file->getId()));
			    	$this->container->get('filed_file.file')->update($file);
			    	$user->addFiles(array($file));
			    	$this->container->get('fos_user.user_manager')->updateUser($user);
	
			    	$uploaded_file->name = $file->getName();
			    	$uploaded_file->size = intval($file->getSize());
			    	$uploaded_file->type = $file->getMime();
	            	$uploaded_file->path = $file->getLink();
	
	            	header('Content-type: application/json');
			    	$response = json_encode(array($uploaded_file));
	            	$this->get('logger')->info('[FileController] Adding '.$file);

	            	//add flash msg to user
	            	$this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.file.add'));
            
	        	}
	        	catch(\Exception $e){

	        		//add flash msg to user
	        		$this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.add'));
	        		
	        		$this->get('logger')->err('[FileController] Error while adding  '.$file);
    				$response = new Response($e->getMessage());
	        	}
    		}
    		else{
    			if($_FILES['files']['error'][0] != 0){
	        		$this->get('logger')->err('[FileController] Error while uploading  file with error code '.$_FILES['files']['error'][0]);
    				
    			}
    			$response = new Response("false");
    		}

    	
    	return new Response($response);
    }


    /**
     * Share a file/repo from the server
     * @param $_POST['id'] id of the parent repo
     * @param $_POST['path'] server path of file/repo to share
     */
    public function addfileserverAction(){
    	try{
    		$path = $_POST['path'];
    		$id = $_POST['id'];
    		$response = "";
    		$this->addFileFromServer($path, $id);
    		
            //add flash msg to user
            $this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.file.add'));
    		
    	}
    	catch(\Exception $e){
            //add flash msg to user
            $this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.add'));
        	
    		$this->get('logger')->err('[FileController] Error while adding file from server '.$path." : ".$e->getCode()." : ".$e->getMessage());
    		$response = $this->container->get('translator')->trans('msg.error.addfileserver.wrongrights').' '.$e->getMessage();
    	}
    	return new Response($response);
    }
    
    /**
     * Add a file/repo from the server
     * @param $path the path of the file/repo to add
     * @param $parent the parent id
     */
    private function addFileFromServer($path,$parent){
   
		//Add the first file
    	if(is_dir($path)){
			$em = $this->container->get('filed_file.directory');
    		$name = basename($path);
    		$mime= FileFactory::getInstance()->getMimeType('dir');
    	}
    	else{
    		//only file
			$em = $this->container->get('filed_file.file');
    	    $name = basename($path);
    	    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    	}
    	//check if it exists by its path
    	$id_find_file = $this->container->get('filed_file.file')->findIdByPath($path);
    	$file=null;
    	if($id_find_file==null || $id_find_file==0){
	    	$file  = $em->create();
	    	$file->setName($name);
	    	$file->setSize(filesize($path));
	    	$user= $this->get('security.context')->getToken()->getUser();
	    	$file->setMime($mime);
	    	$file->setDateCreation(new \DateTime(filectime($path)!=false?'@'.filectime($path):null));
	    	$file->addUsersShare(array($user));
	    	$file->setLink($path);
	    	$file->setExternal(true);
	    	if($parent!=null && $parent!=0){
				$parent_dir = $this->container->get('filed_file.directory')->load($parent);
	    		$file->setParent($parent_dir);
	    		//Apply share options
	    		$users = $parent_dir->getUsersShare();
	    		foreach($users as $u)
	    		{
	    			if($u->getId() != $user->getId())
	    			{
	    				$file->addUsersShare(array($u));
				    	$u->addFiles(array($file));
				    	$this->container->get('fos_user.user_manager')->updateUser($u);
	    			}
	    		}

	    		//add parent author to child author
	    		$file->setAuthor($parent_dir->getAuthor());
	    	}
	    	else{

	    		$file->setAuthor($user);
	    		//share to the admins too
	    		$admins = $this->container->get('filed_user.user')->findAdministrators();
	    		foreach($admins as $admin)
	    		{
	    			//Shared the file to all administrators and escape the current user in case of it's an admin
	    			if($admin->getId()!=$user->getId()){
	    				$file->addUsersShare(array($admin));
	    				$admin->addFiles(array($file));
	    				$this->container->get('fos_user.user_manager')->updateUser($admin);
	    			}
	    		}
	    	}
	    	$em->update($file);

	    	$file->setHash(md5($this->container->getParameter("app_hash_passphrase").$file->getId()));
	    	$em->update($file);
	    	$user->addFiles(array($file));
	    	$this->container->get('fos_user.user_manager')->updateUser($user);
    	}
    	
    	if(is_dir($path)){
    		//Add file for children
    		$filenames = scandir($path);
    		foreach($filenames as $filename){
    			if($filename!="." && $filename !=".."){
					//reconstruct the path
					if(substr($path,-1) != "/"){
						$path.="/";
					}
    				$this->addFileFromServer($path.$filename, $file!=null?$file->getId():$id_find_file);
    			}
    		}
    	}
    }
    
    /**
     * Add a repository
     * @param $_POST['id'] id of the parent repository
     */
    public function addrepositoryAction(){
    	$id = $_POST['id'];
    	
    	$entity  = $this->container->get('filed_file.directory')->create();
    	$entity->setName("New Folder");
    	$user= $this->get('security.context')->getToken()->getUser();
    	if($user!=null){

    		if($id>0){
    			//find the parent
    			$parent = $this->container->get('filed_file.file')->load($id);
    			$entity->setParent($parent);
    			//set parent author
	    		$entity->setAuthor($parent->getAuthor());
    		}
    		else $entity->setAuthor($user);
	    	//enable sharing for the current user
	    	$users = array();
	    	$users[]=$user;
	    	$entity->addUsersShare($users);
	    	$entity->setDateCreation(new \DateTime());
	    	$entity->setSize(0);
	    	$entity->setExternal(true);
	    	$entity->setMime(FileFactory::getInstance()->getMimeType('dir'));
	    	
	    	//create the directory
    	
    		if($entity->getParent()!=null){
    			$path = $entity->getParent()->getLink();
    		}
    		else $path =  __DIR__."/../../../../web/data/uploads/files";
    		
    		
    		$path.="/".date('U');
    		$result = mkdir($path,0755,true);
	    	if($result){
	    		//save the entity
	    		$entity->setLink($path);
	    		$this->container->get('filed_file.file')->update($entity);

		    	$entity->setHash(md5($this->container->getParameter("app_hash_passphrase").$entity->getId()));
	    		$this->container->get('filed_file.file')->update($entity);
		    	$user->addAddedFiles(array($entity));
		    	$user->addFiles(array($entity));
				$this->container->get('fos_user.user_manager')->updateUser($user);

				//add flash msg to user
				$this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.repository.new'));
	    	}
	    	else{

	    		//add flash msg to user
	    		$this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.repository.new'));
	    		return new Response('false');
	    	}
	    	return new Response('true');
    	}
    	else return new Response('false');
    }
    
    /**
     * Render files 
     * @param $files the files to be display
     * @param $fileId the parent id of the files displayed to generate the url to get back
     * @param $last_username property used to render login form
     * @param $csrf_token property used to render login form
     * @param $seen_files the files that is marked as seen
     * @return the rendering view.html.twig with files loaded
     */
    public function viewFilesAction($files,$fileId,$last_username,$csrf_token,$seen_files){
    	
    	$template = sprintf('FileDFileBundle:File:view.html.%s', $this->container->getParameter('fos_user.template.engine'));
    	try{
	    	//Get the parent id
	    	$parent_id=-1;
	    	$file_id=0;
	    	if($fileId!=0){
	    		$file = $this->container->get('filed_file.file')->load($fileId);
	    		$file_id=$file->getId();
		    	if($file!=null && is_object($file)){
			    	$parent = $file->getParent();
			    	if($parent!=null && is_object($parent)){
			    		$parent_id = $parent->getId();
			    	}
			    	else $parent_id=null;
		    	}
		    	else $parent_id=0;
	    	}
	    	
	    	//Map an array idfile=>usernames
	    	//format id:username1,username2;id2:username1
	    	$txt_array_usershare = "";
	    	$j=1;
	    	foreach($files as $file){
	    		$usersshare=$file->getId().":";
	    		$i=1;
	    		foreach($file->getUsersShare() as $user){
	    			$usersshare.=$user->getUsername();
	    			if($i<count($file->getUsersShare()))$usersshare.=",";
	    			
	    			$i++;
	    		}
	    		$txt_array_usershare.=$usersshare;
	    		if($j<count($files)) $txt_array_usershare.=";";
	
	    		$j++;
	    	} 

	    	$this->get('logger')->info('[FileController] Viewing files of  '.$last_username);
	    	
    	}
    	catch(\Exception $e){
    	
    		$this->get('logger')->err('[FileController] Error while viewing files of '.$last_username.' : '.$e->getMessage());
    		
    	}
    	$param_upload = $this->container->get('filed_user.param')->findParameterByKey(ParameterManager::ENABLE_UPLOAD);
    	$param_share = $this->container->get('filed_user.param')->findParameterByKey(ParameterManager::ENABLE_SHARE);
    	$enable_upload = null;
    	$enable_share = null;
    	if($param_upload!=null)$enable_upload = $param_upload[0]->getValue();
    	if($param_share!=null)$enable_share = $param_share[0]->getValue();
    	if($this->container->get('security.context')->isGranted('ROLE_ADMIN')){
    		//if it's the administrator pass through the restriction
    		$enable_share = "1";
    		$enable_upload = "1";
    	}
    	
    	return $this->container->get('templating')->renderResponse($template, 
    			array('files' => $files, 
    				  'seen_files' => $seen_files,
    				  'parent_id' => $parent_id,
    				  'file_id' => $file_id,
    				   'last_username' => $last_username,
    					'txt_usershare'=> $txt_array_usershare,
    					'csrf_token' => $csrf_token,
    					'enable_upload' => $enable_upload,
    					'enable_share' => $enable_share));
    }
    


    /**
     * Render file link to view file content
     * @param $id the file id
     * @return the rendering of display view depending of file type
     */
    public function viewFileAction($id)
    {
		try{
    		$file = $this->container->get('filed_file.file')->load($id);
    		$handle = FileFactory::getInstance()->isTypeHandle($file);
    		//Generate web link of the file
    		$links = explode("/web/", $file->getLink());
    		if(count($links)>1)$weblink = $links[1];
    		else new Response(""); //the file is external so impossible to view it
    		if($handle == FileFactory::AUDIO){
    			$template = 'FileDFileBundle:View:audio.html.twig';
    			$response=$this->container->get('templating')->renderResponse($template,
    					array("link" => $weblink,
    							"name" => $file->getName(),
    							"id" => $file->getId(),
    							"format"=> FileFactory::getInstance()->getAudioFormat($file)));
    		}
    		else if($handle == FileFactory::IMG){
    			$template = 'FileDFileBundle:View:image.html.twig';
    			$response=$this->container->get('templating')->renderResponse($template,
    					array("link" => $weblink,
    							"name" => $file->getName()));
    		}
    		else if($handle == FileFactory::VIDEO){
    			$template = 'FileDFileBundle:View:video.html.twig';
    			$response=$this->container->get('templating')->renderResponse($template,
    					array("link" => $weblink,
    							"name" => $file->getName(),
    							"id" => $file->getId(),
    							"format"=> FileFactory::getInstance()->getVideoFormat($file)));
    		}
    		else $response = new Response("");	    	
    	}
    	catch(\Exception $e){
    		$response = new Response("");
    		$this->get('logger')->err('[FileController] Error while viewing file of '.$id.' : '.$e->getMessage());
    		
    	}
    	return $response;
    }
    /**
     * Edit a file (name only) from the list view
     * @param $_POST['id'] the id of the file
     * @param $_POST['name'] the name of the file
     * @return the new name
     */
    public function editAction()
    {
    	try{
	    	$id = $_POST['id'];
	    	$name = $_POST['name'];
	    	$file = $this->container->get('filed_file.file')->load($id);
	    	$file->setName($name);
	    	$this->container->get('filed_file.file')->update($file);
	    	$this->get('logger')->info('[FileController] Edit '.$file);
	            //add flash msg to user
	            $this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.file.edit'));
	    	return new Response($name);
    	}
    	catch(\Exception $e){
	            //add flash msg to user
	            $this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.edit'));

    		$this->get('logger')->err('[FileController] Error while editing file '.$e->getCode().': '.$e->getMessage());
    		return new Response($e->getMessage());
    	}
    	
    }
    


    /**
     * Deletes a File entity.
     * @param $_POST['id'] the id of the file
     * @return true or false
     */
    public function deleteAction()
    {
    	$resu = null;
    	try{
	    	$id = $_POST['id'];
	    	//Delete the local file/directory
	    	$file = $this->container->get('filed_file.file')->load($id);
	    	if($file->isDirectory()){
	    		$dir = $file->getLink();
    			$this->rrmdir($dir);
	    	}
	    	else $resu = unlink($file->getLink());
	    	$this->get('logger')->info('[FileController] Delete file with id '.$_POST['id']);
            //add flash msg to user
            $this->container->get('session')->setFlash('success', $this->container->get('translator')->trans('msg.success.file.delete'));
    	}
    	catch(\Exception $e){
            //add flash msg to user
            $this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.delete'));
	    	$this->get('logger')->err('[FileController] Error while deleting file with id '.$_POST['id']);
    		
    	}

    	$this->container->get('filed_file.file')->delete($id);
    	
    	return new Response("true");
    	
    }
    
    /**
     * Get the size of a directory 
     * @param $id the id of the directory
     * @return string the size
     */
    public function getSizeDirectoryAction($id){
		$size=0;
    	$file = $this->container->get('filed_file.file')->load($id);
    	$this->get('logger')->debug('[FileController] Calculate size of file with id '.$id);
    	if($file!=null){
    		$size=$this->recursiveSize($file, $size);
    	}
    	if($size < 1000){
    		$response = $size." ".$this->container->get('translator')->trans('file.size.unit');
    	}
    	else if($size < 1000000){
    		$response = ($size/1000)." ".$this->container->get('translator')->trans('file.size.unit.kilo');
    	}
    	else if($size < 1000000000){
    		$response = ($size/1000000)." ".$this->container->get('translator')->trans('file.size.unit.mega');
    	}
    	else if($size < 1000000000000){
    		$response = ($size/1000000000)." ".$this->container->get('translator')->trans('file.size.unit.giga');
    	}
    	else{
    		$response = ($size/1000000000000)." ".$this->container->get('translator')->trans('file.size.unit.tera');
    	}
    	return new Response($response);
    }
    
    /**
     * Define the size recursively of file/dir given
     * @param File $file
     * @param Integer $size
     * @return the size
     */
    private function recursiveSize($file,$size){
    	if(!$file->isDirectory())
    	{
    		$size+=$file->getSize();

    		$this->get('logger')->debug('[FileController] Adding size '.$file->getSize().' of file with id '.$file->getId());
    	}
    	else if($file->getChildren()->count()>0){
    		foreach($file->getChildren() as $child){
    			$size = $this->recursiveSize($child, $size);
    		}
    	}
    	return $size;
    }
    
    /**
     * Download the file/dir
     * (zipped if directory)
     * @param integer $id
     * @return Response download window
     */
    public function downloadAction($id)
    {
		$response = new Response();
    	$file = $this->container->get('filed_file.file')->load($id);
        $user = $this->container->get('security.context')->getToken()->getUser();
        try{	    		
		    
	    	if($file!=null && FileFactory::getInstance()->isSharedWith($user,$file)){
	    		if($file->isDirectory()){
	    			//Zip directory and store it locally to process download
	    			//First : clean the directory of stored zip files
	    			$this->clearDirectory(__DIR__.FileController::DOWNLOAD_DIR);
	    			//Then add the new one
	    			//$zip = new \ZipArchive();
	    			$name = $file->getName().".zip";
	    			//$dirname = basename($file->getName());
	    			//$path = __DIR__."/../../../../web/data/downloads/zip/".$name;
        	        /*$this->get('logger')->info('[FileController] Zipping directory '.$name.' with path '.$path);
	    			$zip->open($path, \ZipArchive::CREATE);
	    			$zip = $this->addToZip($zip, $file, "/", true);
	    			$zip->close();
	    			$mime = "application/zip";*/
	    			 $path = $file->getLink();
	    			 $name = $file->getName();
	    			 $mime = $file->getMime();
	    			
	    		}
	    		else{
	    			 $path = $file->getLink();
	    			 $name = $file->getName();
	    			 $mime = $file->getMime();
	    		}
	    		
	    		if(!file_exists($path)) throw new \Exception("The file didn't exist anymore");
	    		
	    		$response = new Response();
	    		$response->setStatusCode(200);
	    		$response->headers->set('Content-Type', "application/octet-stream");
	    		$response->headers->set('Content-Disposition', 'attachment; filename="'.$name.'"');
	    		$response->headers->set('X-Sendfile', $path);
        	    $this->get('logger')->info('[FileController] Downloading file with id '.$id);
        	    $this->get('logger')->info('[FileController] Downloading file render: '.$response->__toString());
	    		$response->send();
	    	}
	    	else{

	    		$this->get('logger')->err('[FileController] Error while downloading file with id '.$id);
	    	}
        }
        catch(\Exception $e){
            //add flash msg to user
            $this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.download'));

        	$this->get('logger')->err('[FileController] Error while downloading file with id '.$id);
        }
    	return $response;
    }
    
	/**
	 * Add a file/directory to a zip
	 * @param ZipArchive $zip
	 * @param File $file 
	 * @param bool isRoot
	 * @return the zip filled
	 */
    private function addToZip($zip, $file, $filename,$isRoot)
    {
    	if($file->isDirectory()){
			//add an empty dir with the name
			$filename .=$file->getName()."/";
    		$zip->addEmptyDir($filename);
    		foreach($file->getChildren() as $child){
    			$zip = $this->addToZip($zip,$child,$filename,false);
    		}
    	}
    	else{
    		//add the file to the zip
    		$zip->addFile($file->getLink(), $filename.$file->getName());
    	}
    	
    	return $zip;
    }
    
    
    /**
     * Clear the directory content
     * @param string $path the path of the directory
     */
    private function clearDirectory($path)
    {
    
    	$dir=opendir($path);
    	while ($fichier = readdir($dir))
    	{
    		if ($fichier != "." && $fichier != "..")
    		{
    			$this->rrmdir($path."/".$fichier);
    		}
    	}
    	closedir($dir);
    }
    
    /**
     * Remove a directory and its content
     * @param string $dir path to directory
     */
    private function rrmdir($dir) {
    	foreach(glob($dir . '/*') as $file) {
    		if(is_dir($file))
    			$this->rrmdir($file);
    		else
    			unlink($file);
    	}

    	if(is_dir($dir))
    		rmdir($dir);
    	else
    		unlink($dir);
    }

    /**
     * Share a file/repository to a list of users
     * @param Request the request
     * @return redirection to index
     */
    public function shareFileAction(Request $request)
    {
    	$template = sprintf('FileDFileBundle:File:share.html.%s', $this->container->getParameter('fos_user.template.engine'));
    	
    	$users = $this->container->get('filed_user.user')->findActiveUsers();
    	$choices = array();
    	foreach($users as $user){
    		$choices[$user->getId()] = $user->getUsername();
    	}
    	$shareclass = new Share();
    	$form = $this->container->get('form.factory')->create(new ShareFileType($choices), $shareclass,array('label'=>$this->container->get('translator')->trans('file.share.list.label')));
    	try{
	    	if ($request->getMethod() == 'POST') { 
	    		$form->bindRequest($request); 
	    	
	    		if ($form->isValid()) {
	    			$file_id = $shareclass->getId();
	    			$users = $shareclass->getUsers();

	    			$file = $this->container->get('filed_user.file')->load($file_id);
	    			$file = $this->shareAll($file,$users,true);
	    			
            		$this->container->get('session')->setFlash('success',$this->container->get('translator')->trans('msg.success.file.share'));
	    			return new RedirectResponse($this->container->get('router')->generate('user_index'));
	    		}
	    	}
    	}
    	catch(\Exception $e){

    		$this->container->get('session')->setFlash('error',$this->container->get('translator')->trans('msg.error.file.share'));
    		$this->get('logger')->err('[FileController] Error while sharing file with id '.$shareclass->getId()." : ".$e->getMessage());
    	}
    	return $this->container->get('templating')->renderResponse($template,
    			array(
    					'form' => $form->createView()
    				));
    }
    
    /**
     * Share file and its content to the selected users 
     * @param File the file
     * @param array of Users
     * @param boolean define that the directory content isn't shared
     */
    private function shareAll($file,$users,$sharedContent=true)
    {
    	//Reset sharing option
    	//$file->setUsersShare(new \Doctrine\Common\Collections\ArrayCollection());
    	$this->container->get('filed_file.file')->update($file);
    	$usershare = $file->getUsersShare();
    	$users_added = array();
    	foreach($users as $id_user){
    		$users_added[$id_user]=$users;
    		//Foreach user add it to file if not already in there
    		$user = $this->container->get('filed_user.user')->load($id_user);
    		$p = function($key, $element) use ($id_user){
    			return $element->getId() == $id_user;
    		};
    		//do not add it if the user is already shared
    		if($usershare==null || !$usershare->exists($p)){
    			$user->addFiles(array($file));
    			$file->addUsersShare(array($user));
    			$this->container->get('filed_user.user')->update($user);
    			$this->get('logger')->info('[FileController] Sharing file '.$file->getId().' to user id'.$id_user);
    		}
    	}
    	
    	//TODO need better algorithm...
    	if(count($users_added) != $file->getUsersShare()->count()){
    		//Find the ones to remove
    		foreach($file->getUsersShare() as $user){
    			if(!array_key_exists($user->getId(),$users_added)){
    				//remove it
    				$this->get('logger')->info('[FileController] Unsharing file '.$file->getId().' to user id'.$user->getId());
    				$file->removeUsersShare(array($user));
    				$user->removeFiles(array($file));
    				$this->container->get('filed_user.user')->update($user);
    					
    			}
    		}
    	}
    	$this->container->get('filed_file.file')->update($file);
    	
    	//If this is a directory loop on child to apply sharing options
    	if($file->isDirectory() && $sharedContent)
    	{
    		foreach($file->getChildren() as $child)
    		{
    			$this->shareAll($child, $users,true);
    		}		
    	}
    	
    	//Shared all directories parents
    	if($file->getParent()!=null)
    	{
    			$this->shareAll($file->getParent(), $users, false);
    	}
    	
    	$this->container->get('filed_file.file')->update($file);
    	
    	return $file;
    }
    
    /**
     * View the part link dealing with mark as seen functionality in the table of files
     * @param integer the file id 
     * @return response
     */
    public function isMarkAsSeenFileAction($id){
    	$file = $this->container->get('filed_file.file')->load($id);
        $user = $this->container->get('security.context')->getToken()->getUser();
        $exists = false;

	    //text for marked as seen
	    $txt_userseen = "";
    	$i=1;
    	foreach($file->getUsersSeen() as $user1){
    		$txt_userseen.=$user1->getUsername();
    		if($i<count($file->getUsersSeen()))$txt_userseen.=",";
    		if($user->getId()==$user1->getId()){
    			$exists=true;
    		}	
    		$i++;
    	}

    	$title = $this->container->get('translator')->trans('file.list.name.mark');
    	 
    	if($txt_userseen!=""){
    		$title.=" ".$this->container->get('translator')->trans('file.list.name.mark.by')." ".$txt_userseen;
    	}
	    
	    if($exists){
        	//Marked as seen
        	$response = '<a href="" title="'.$title.'" onclick="markAsSeen('.$id.',0)"><i title="'.$title.'" class="icon-ok"></i></a>';
        }
        else{
        	//didn't marked as seen
        	$response = '<a href="" title="'.$title.'" onclick="markAsSeen('.$id.',1)"><i class="icon-ok-circle"></i></a>';
        }
        
        return new Response($response);
    }
    
    /**
     * Mark a file as seen
     * @param POST integer the file id
     * @param POST boolean if the file is marked
     * @return response
     */
    public function markAsSeenAction()
    {
    	$id = $_POST['id'];
    	$marked = $_POST['marked'];
    	$file = $this->container->get('filed_file.file')->load($id);
        $user = $this->container->get('security.context')->getToken()->getUser();
        $this->markAsSeen($file,$user,$marked==1?true:false);
        return new Response('');
    }
    
    /**
     * Mark a file or directory of sub file
     * @param $file the file
     * @param $user the user
     * @param boolean marked or not
     */
    private function markAsSeen($file,$user,$marked)
    {
    	if($file->isDirectory())
    	{
    		foreach($file->getChildren() as $child)
    		{
    			$this->markAsSeen($child, $user,$marked);		
    		}
    	}
    	
    	if($marked){
	        $file->addUsersSeen(array($user));
	        $user->addSeenFiles(array($file));
    	}
    	else{
	        $file->getUsersSeen()->removeElement($user);
	        $user->getSeenFiles()->removeElement($file);
    	}
    	$this->container->get('filed_user.user')->update($user);
    	$this->container->get('filed_file.file')->update($file);
    	
    }
    
    /**
     * Refresh an external directory (a file which isn't inside the application directory)
     * @param POST integer the file id
     * @return response
     */
    public function refreshAction()
    {
    	$id = $_POST['id'];
    	$file = $this->container->get('filed_file.file')->load($id);
    	if($file!=null)
    	{
    		$this->addFileFromServer($file->getLink(), $file->getParent()!=null?$file->getParent()->getId():null);
	        $user = $this->container->get('security.context')->getToken()->getUser();
	        $this->markAsSeen($file,$user);
    	}
        return new Response('');
    }
    
    /**
     * Get a file with a public link
     * @param string the hash
     * @return download return response
     */
    public function publicLinkAction($hash)
    {
    	//determine the file from the hash
    	$file = $this->container->get('filed_file.file')->findFileByHash($hash);

    	try{
	    	if($file!=null)
	    	{
    	$template = sprintf('FileDFileBundle:File:public_download.html.%s', $this->container->getParameter('fos_user.template.engine'));
		        	return $this->container->get('templating')->renderResponse($template,
		        			array(
		        					'file' => $file
		        			));	        	
	    	}
	        else{
	        	$this->get('logger')->error('[FileController] Trying to access public file with hash '.$hash.' with ip '.$container->get('request')->getClientIp());
	        	$this->container->get('session')->setFlash('error',$this->container->get('translator')->trans('msg.error.file.public'));
	        	return new RedirectResponse($this->container->get('router')->generate('user_index'));
	        }
        }
        catch(\Exception $e){
        $this->get('logger')->error('[FileController] Trying to access public file with hash '.$hash.' with ip '.$container->get('request')->getClientIp());
        		$this->container->get('session')->setFlash('error',$this->container->get('translator')->trans('msg.error.file.public'));
        		return new RedirectResponse($this->container->get('router')->generate('user_index'));
        }
    }
    
    /**
     * Download the file/dir anonymously
     * (zipped if directory)
     * @param integer $id
     * @return Response download window
     */
    public function downloadPublicAction($id)
    {
    	$response = new Response();
    	$file = $this->container->get('filed_file.file')->load($id);
    	try{
    
    		if($file!=null){
    			if($file->isDirectory()){
    				//Zip directory and store it locally to process download
    				//First : clean the directory of stored zip files
    				$this->clearDirectory(__DIR__.FileController::DOWNLOAD_DIR);
    				//Then add the new one
    				$zip = new \ZipArchive();
    				$name = $file->getName().".zip";
    				$dirname = basename($file->getName());
    				$path = __DIR__."/../../../../web/data/downloads/zip/".$name;
    				$zip->open($path, \ZipArchive::CREATE);
    				$zip = $this->addToZip($zip, $file, "/", true);
    				$zip->close();
    				$mime = "application/zip";
    
    			}
    			else{
    				$path = $file->getLink();
    				$name = $file->getName();
    				$mime = $file->getMime();
    			}
    	   
    			$response = new Response();
    			$response->setStatusCode(200);
    			$response->headers->set('Content-Type', "application/".$mime);
    			$response->headers->set('Content-Disposition', sprintf('attachment;filename="%s"', $name, $mime));
    			$response->setCharset('UTF-8');
    			$response->setContent(file_get_contents($path));
    			// prints the HTTP headers followed by the content
    			$response->send();
    		}
    		$this->get('logger')->info('[FileController] Downloading file with id '.$id);
    	}
    	catch(\Exception $e){
    		//add flash msg to user
    		$this->container->get('session')->setFlash('error', $this->container->get('translator')->trans('msg.error.file.download'));
    
    		$this->get('logger')->err('[FileController] Error while downloading file with id '.$id);
    	}
    	return $response;
    }

}
